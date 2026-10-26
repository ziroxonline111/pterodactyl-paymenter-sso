<?php

declare(strict_types=1);

namespace KareliaLabs\PaymenterSso\Paymenter;

use Pterodactyl\Models\User;
use RuntimeException;

/** Why a sign-in with Paymenter did not sign anyone in, with the message the login page shows. */
final class Refusal extends RuntimeException
{
    public const string UNAVAILABLE = 'unavailable';

    public const string EXPIRED = 'expired';

    public const string DENIED = 'denied';

    public const string UNREACHABLE = 'unreachable';

    public const string MISCONFIGURED = 'misconfigured';

    public const string UNVERIFIED = 'unverified';

    public const string NO_ACCOUNT = 'no_account';

    public const string ADMIN = 'admin';

    /** Refusals about a real Paymenter account; they are written to the panel's activity log. */
    public const array ABOUT_ACCOUNT = [self::UNVERIFIED, self::NO_ACCOUNT, self::ADMIN];

    public function __construct(
        public readonly string $reason,
        public readonly ?Account $account = null,
        public readonly ?User $user = null,
    ) {
        parent::__construct('Sign in with Paymenter refused: '.$reason.'.');
    }

    public function message(): string
    {
        return match ($this->reason) {
            self::UNAVAILABLE => 'Sign in with Paymenter is not available on this panel. Please sign in with your password.',
            self::EXPIRED => 'That sign-in attempt has expired or was started in another browser. Please try again.',
            self::DENIED => 'Sign-in with Paymenter was cancelled.',
            self::UNREACHABLE => 'The panel could not reach Paymenter. Please try again in a moment, or sign in with your password.',
            self::MISCONFIGURED => 'Sign in with Paymenter is not set up correctly on this panel. Please sign in with your password and let support know.',
            self::UNVERIFIED => 'The email address of your Paymenter account is not verified yet. Verify it in Paymenter, then try again.',
            self::NO_ACCOUNT => sprintf('No panel account uses the email address of your Paymenter account (%s). If your panel account has another address, sign in with your password.', $this->account?->email ?? ''),
            self::ADMIN => 'Administrator accounts cannot sign in with Paymenter on this panel. Please sign in with your password.',
        };
    }
}
