<?php

declare(strict_types=1);

namespace KareliaLabs\PaymenterSso\Paymenter;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use KareliaLabs\PaymenterSso\PaymenterSsoProvider;

/**
 * Paymenter's OAuth 2.0 server (Laravel Passport), at the endpoints Paymenter documents: GET /oauth/authorize,
 * POST /api/oauth/token and GET /api/me with the scope "profile". The access token is used for the one /api/me
 * request and then dropped; it is never stored.
 */
final readonly class Paymenter
{
    private const string SCOPE = 'profile';

    public function __construct(
        public string $url,
        private string $clientId,
        private string $clientSecret,
        private string $callbackUrl,
    ) {}

    /**
     * Where the browser goes to sign in to Paymenter and approve the panel.
     *
     * prompt=consent makes Paymenter show its authorization page every time. Without it, a customer who approved the
     * panel before and then signs in on Paymenter's login page is stuck there: Paymenter 1.5.9 opens the next page
     * with a JavaScript fetch, which the browser does not let follow the redirect back to the panel.
     */
    public function authorizeUrl(string $state, string $codeChallenge): string
    {
        return $this->url.'/oauth/authorize?'.http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $this->callbackUrl,
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
            'prompt' => 'consent',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Exchanges the code from the callback for an access token and reads the account it belongs to.
     *
     * @throws Refusal
     */
    public function account(string $code, string $codeVerifier): Account
    {
        $token = $this->send(fn (PendingRequest $http): Response => $http->asForm()->post($this->url.'/api/oauth/token', [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->callbackUrl,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code_verifier' => $codeVerifier,
        ]), 'token');

        $accessToken = $token->json('access_token');
        if (! is_string($accessToken) || $accessToken === '') {
            throw $this->unexpected('token', $token);
        }

        $me = $this->send(fn (PendingRequest $http): Response => $http->withToken($accessToken)->get($this->url.'/api/me'), 'me');
        $account = Account::fromProfile($me->json());
        if ($account === null) {
            throw $this->unexpected('me', $me);
        }

        return $account;
    }

    /**
     * @param  Closure(PendingRequest): Response  $request
     *
     * @throws Refusal
     */
    private function send(Closure $request, string $step): Response
    {
        $http = Http::acceptJson()
            ->withUserAgent('Pterodactyl-Sign-in-with-Paymenter/'.PaymenterSsoProvider::VERSION)
            ->connectTimeout(5)
            ->timeout(10)
            ->withoutRedirecting();

        try {
            $response = $request($http);
        } catch (ConnectionException $exception) {
            Log::warning('Sign in with Paymenter: Paymenter could not be reached.', ['url' => $this->url, 'step' => $step, 'error' => $exception->getMessage()]);

            throw new Refusal(Refusal::UNREACHABLE);
        }

        if ($response->successful()) {
            return $response;
        }

        // Passport answers invalid_client (401) for an unknown client ID or a wrong secret, and invalid_grant (400)
        // for a code that was already used, has expired, or does not match the code verifier or the redirect URL.
        $error = $response->json('error');
        if ($step === 'token' && $error === 'invalid_grant') {
            throw new Refusal(Refusal::EXPIRED);
        }
        if ($step === 'token' && $error === 'invalid_client') {
            Log::warning('Sign in with Paymenter: Paymenter refused the client ID or the client secret (invalid_client). Check both in the extension\'s settings.', ['url' => $this->url]);

            throw new Refusal(Refusal::MISCONFIGURED);
        }

        throw $this->unexpected($step, $response);
    }

    private function unexpected(string $step, Response $response): Refusal
    {
        Log::warning('Sign in with Paymenter: Paymenter gave an unexpected answer.', [
            'url' => $this->url,
            'step' => $step,
            'status' => $response->status(),
            'error' => is_string($response->json('error')) ? $response->json('error') : null,
            'location' => $response->header('Location') ?: null,
        ]);

        return new Refusal(Refusal::MISCONFIGURED);
    }
}
