<?php

declare(strict_types=1);

namespace KareliaLabs\PaymenterSso;

use Pterodactyl\Extensions\ExtensionProvider;
use Pterodactyl\Services\Extensions\ExtensionSettingDefinition;
use Pterodactyl\Services\Extensions\ExtensionSettingsDefinition;

/**
 * "Sign in with Paymenter" on the panel's login page: an OAuth 2.0 authorization code flow (with state and PKCE)
 * against the OAuth server Paymenter has built in.
 *
 * - Web routes (/extensions/paymenter-sso): the login page's state, the start of a sign-in and Paymenter's callback.
 * - The button itself is the frontend's component in the login page's `auth.login.form.after` slot.
 * - The session is made by the panel's own CompletesLogins action, so two-factor authentication still applies.
 */
final class PaymenterSsoProvider extends ExtensionProvider
{
    public const string ID = 'paymenter-sso';

    public const string VERSION = '1.0.0';

    public function boot(): void
    {
        $this->registerWebRoutes($this->extensionPath('routes', 'web.php'));

        $this->registerSettings(new ExtensionSettingsDefinition($this->settings(), [
            ExtensionSettingDefinition::make(Settings::URL, Settings::URL, '', ['nullable', 'string', 'max:255', new PaymenterUrl()])
                ->label('Paymenter URL')
                ->help('The address of your Paymenter, as customers open it, such as https://billing.example.com. It has to start with https:// (http:// only for localhost).')
                ->normalizeUsing(fn (mixed $value): string => is_string($value) ? rtrim(trim($value), '/') : ''),
            ExtensionSettingDefinition::make(Settings::CLIENT_ID, Settings::CLIENT_ID, '', ['nullable', 'string', 'max:255'])
                ->label('Client ID')
                ->help('From Paymenter: Admin, OAuth Clients. The client\'s redirect URL must be exactly '.Settings::callbackUrl())
                ->normalizeUsing(fn (mixed $value): string => is_string($value) ? trim($value) : ''),
            ExtensionSettingDefinition::make(Settings::CLIENT_SECRET, Settings::CLIENT_SECRET, '', ['nullable', 'string', 'max:255'])
                ->label('Client secret')
                ->help('Shown once by Paymenter when the client is created. Stored encrypted; leave the field empty to keep the stored secret.')
                ->field('password'),
            ExtensionSettingDefinition::make(Settings::LABEL, Settings::LABEL, Settings::DEFAULT_LABEL, ['nullable', 'string', 'max:60'])
                ->label('Button label')
                ->help('The text of the button on the login page.')
                ->normalizeUsing(fn (mixed $value): string => is_string($value) && trim($value) !== '' ? trim($value) : Settings::DEFAULT_LABEL),
            ExtensionSettingDefinition::make(Settings::ALLOW_ADMINS, Settings::ALLOW_ADMINS, false, ['boolean'])
                ->label('Allow root administrators to sign in with Paymenter')
                ->help('Off by default: root administrator accounts sign in with their password. Anyone who controls a matching Paymenter account could otherwise sign in as an administrator.')
                ->field('toggle'),
        ]));
    }
}
