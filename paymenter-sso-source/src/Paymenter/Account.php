<?php

declare(strict_types=1);

namespace KareliaLabs\PaymenterSso\Paymenter;

/** The Paymenter account from GET /api/me: Paymenter's User model without its hidden fields. */
final readonly class Account
{
    public function __construct(
        public int $id,
        public string $email,
        public bool $emailVerified,
    ) {}

    public static function fromProfile(mixed $profile): ?self
    {
        if (! is_array($profile)) {
            return null;
        }

        $id = $profile['id'] ?? null;
        $email = $profile['email'] ?? null;
        if (! is_int($id) || $id < 1 || ! is_string($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        // Paymenter sets email_verified_at when the customer opens the link in its verification email, or when an
        // admin switches on "Email Verified" for the user, and clears it when the customer changes the address.
        $verifiedAt = $profile['email_verified_at'] ?? null;

        return new self($id, $email, is_string($verifiedAt) && $verifiedAt !== '');
    }
}
