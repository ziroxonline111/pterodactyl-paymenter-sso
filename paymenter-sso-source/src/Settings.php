<?php

declare(strict_types=1);

namespace KareliaLabs\PaymenterSso;

use KareliaLabs\PaymenterSso\Paymenter\Paymenter;
use Pterodactyl\Facades\Extensions;
use Pterodactyl\Services\Extensions\ExtensionSettings;

/** The settings on the panel's Extensions page (Sign in with Paymenter, Settings). */
final class Settings
{
    public const string URL = 'paymenter_url';

    public const string CLIENT_ID = 'client_id';

    public const string CLIENT_SECRET = 'client_secret';

    public const string LABEL = 'button_label';

    public const string ALLOW_ADMINS = 'allow_root_admins';

    public const string DEFAULT_LABEL = 'Sign in with Paymenter';

    /** Where Paymenter sends the customer back to. It has to match the OAuth client's redirect URL exactly. */
    public static function callbackUrl(): string
    {
        return rtrim((string) config('app.url'), '/').'/extensions/'.PaymenterSsoProvider::ID.'/callback';
    }

    /** The Paymenter connection, or null while the URL, the client ID or the secret is missing. */
    public static function paymenter(): ?Paymenter
    {
        $url = self::string(self::URL);
        $clientId = self::string(self::CLIENT_ID);
        $secret = self::string(self::CLIENT_SECRET);
        if ($url === '' || $clientId === '' || $secret === '' || ! PaymenterUrl::accepts($url)) {
            return null;
        }

        return new Paymenter($url, $clientId, $secret, self::callbackUrl());
    }

    public static function label(): string
    {
        $label = self::string(self::LABEL);

        return $label === '' ? self::DEFAULT_LABEL : $label;
    }

    public static function allowRootAdmins(): bool
    {
        return self::settings()->get(self::ALLOW_ADMINS, false) === true;
    }

    private static function string(string $key): string
    {
        $value = self::settings()->get($key, '');

        return is_string($value) ? trim($value) : '';
    }

    private static function settings(): ExtensionSettings
    {
        return Extensions::settings(PaymenterSsoProvider::ID);
    }
}
