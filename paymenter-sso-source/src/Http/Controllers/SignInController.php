<?php

declare(strict_types=1);

namespace KareliaLabs\PaymenterSso\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use KareliaLabs\PaymenterSso\LocalPath;
use KareliaLabs\PaymenterSso\Models\Link;
use KareliaLabs\PaymenterSso\Paymenter\Account;
use KareliaLabs\PaymenterSso\Paymenter\Paymenter;
use KareliaLabs\PaymenterSso\Paymenter\Refusal;
use KareliaLabs\PaymenterSso\Settings;
use Pterodactyl\Contracts\Users\CompletesLogins;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Models\User;

/**
 * The three steps of a sign-in with Paymenter. All of them keep their state in the visitor's panel session:
 *
 * 1. state: what the login page shows (the button, a message from the last attempt, a pending two-factor checkpoint);
 * 2. start (POST, with the panel's CSRF protection): makes the state and the PKCE verifier and returns Paymenter's
 *    authorization URL;
 * 3. callback: checks the state, exchanges the code, finds the panel account and hands it to the panel's own login.
 */
final class SignInController
{
    private const string FLOW = 'paymenter_sso.flow';

    private const string NOTICE = 'paymenter_sso.notice';

    private const string CHECKPOINT = 'paymenter_sso.checkpoint';

    /** How long the customer has to sign in to Paymenter and come back. */
    private const int FLOW_SECONDS = 600;

    public function __construct(private readonly CompletesLogins $logins) {}

    public function state(Request $request): JsonResponse
    {
        $session = $request->session();
        $checkpoint = $session->pull(self::CHECKPOINT) === true ? $this->logins->pendingCheckpoint() : null;
        $notice = $session->pull(self::NOTICE);

        return new JsonResponse(['data' => [
            'button' => $request->user() === null && Settings::paymenter() !== null ? Settings::label() : null,
            'notice' => is_string($notice) ? $notice : null,
            'checkpoint' => $checkpoint?->token,
        ]]);
    }

    public function start(Request $request): JsonResponse
    {
        if ($request->user() !== null) {
            return new JsonResponse(['data' => ['url' => '/']]);
        }

        $paymenter = Settings::paymenter();
        if ($paymenter === null) {
            return new JsonResponse(['errors' => [['code' => 'NotFound', 'status' => '404', 'detail' => (new Refusal(Refusal::UNAVAILABLE))->message()]]], 404);
        }

        $state = Str::random(40);
        $verifier = Str::random(64);
        $request->session()->put(self::FLOW, [
            'state' => $state,
            'verifier' => $verifier,
            'redirect' => LocalPath::from($request->input('redirect')),
            'expires' => time() + self::FLOW_SECONDS,
        ]);

        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        return new JsonResponse(['data' => ['url' => $paymenter->authorizeUrl($state, $challenge)]]);
    }

    public function callback(Request $request): RedirectResponse
    {
        $flow = $request->session()->pull(self::FLOW);

        try {
            $paymenter = Settings::paymenter() ?? throw new Refusal(Refusal::UNAVAILABLE);
            [$verifier, $redirect] = $this->verifiedFlow($flow, $request->query('state'));
            if ($request->query('error') !== null) {
                throw new Refusal(Refusal::DENIED);
            }
            $code = $request->query('code');
            if (! is_string($code) || $code === '' || strlen($code) > 4096) {
                throw new Refusal(Refusal::EXPIRED);
            }

            $account = $paymenter->account($code, $verifier);
            [$user, $matchedBy] = $this->panelUser($paymenter, $account);
        } catch (Refusal $refusal) {
            if (in_array($refusal->reason, Refusal::ABOUT_ACCOUNT, true)) {
                Activity::event('paymenter-sso:refused')
                    ->withRequestMetadata()
                    ->subject($refusal->user)
                    ->property(['reason' => $refusal->reason, 'paymenter_user_id' => $refusal->account?->id, 'email' => $refusal->account?->email])
                    ->log();
            }
            $request->session()->put(self::NOTICE, $refusal->message());

            return redirect()->to('/auth/login');
        }

        Link::query()->updateOrCreate(['user_id' => $user->id], [
            'paymenter_url' => $paymenter->url,
            'paymenter_user_id' => $account->id,
            'last_login_at' => now(),
        ]);

        Activity::event('paymenter-sso:login')
            ->withRequestMetadata()
            ->subject($user)
            ->property(['paymenter_user_id' => $account->id, 'matched_by' => $matchedBy, 'two_factor' => (bool) $user->use_totp])
            ->log();

        if ($this->logins->complete($user)->complete) {
            return redirect()->to($redirect);
        }

        // Two-factor authentication: the panel has stored its checkpoint in the session. The login page asks for it
        // (state) and opens the panel's own checkpoint screen, which finishes the sign-in.
        $request->session()->put(self::CHECKPOINT, true);

        return redirect()->to('/auth/login'.($redirect === '/' ? '' : '?'.http_build_query(['redirect' => $redirect])));
    }

    /**
     * The sign-in this callback belongs to: started in this session, not expired, and with the same state.
     *
     * @return array{string, string} the PKCE verifier and where to go afterwards
     *
     * @throws Refusal
     */
    private function verifiedFlow(mixed $flow, mixed $state): array
    {
        if (! is_array($flow) || ! is_string($flow['state'] ?? null) || ! is_string($flow['verifier'] ?? null)
            || ! is_string($flow['redirect'] ?? null) || ! is_int($flow['expires'] ?? null) || $flow['expires'] < time()
            || ! is_string($state) || ! hash_equals($flow['state'], $state)) {
            throw new Refusal(Refusal::EXPIRED);
        }

        return [$flow['verifier'], $flow['redirect']];
    }

    /**
     * The panel account for a Paymenter account: the one it signed in to before, otherwise the one with the same
     * email address, which Paymenter must have verified.
     *
     * @return array{User, 'account'|'email'}
     *
     * @throws Refusal
     */
    private function panelUser(Paymenter $paymenter, Account $account): array
    {
        $link = Link::query()->with('user')
            ->where('paymenter_url', $paymenter->url)
            ->where('paymenter_user_id', $account->id)
            ->first();

        if ($link !== null) {
            [$user, $matchedBy] = [$link->user, 'account'];
        } else {
            if (! $account->emailVerified) {
                throw new Refusal(Refusal::UNVERIFIED, $account);
            }
            $user = User::query()->where('email', $account->email)->first() ?? throw new Refusal(Refusal::NO_ACCOUNT, $account);
            $matchedBy = 'email';
        }

        if ($user->root_admin && ! Settings::allowRootAdmins()) {
            throw new Refusal(Refusal::ADMIN, $account, $user);
        }

        return [$user, $matchedBy];
    }
}
