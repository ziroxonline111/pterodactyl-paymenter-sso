<?php

declare(strict_types=1);

namespace KareliaLabs\PaymenterSso;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The Paymenter URL setting: https:// with a host and nothing after the path, or http:// for a Paymenter on the
 * same machine (localhost, 127.0.0.1, [::1], *.localhost). The client secret and the customers' codes are sent there.
 */
final class PaymenterUrl implements ValidationRule
{
    private const array LOOPBACK = ['localhost', '127.0.0.1', '[::1]'];

    public static function accepts(string $url): bool
    {
        $url = rtrim(trim($url), '/');
        $parts = parse_url($url);
        if ($parts === false || ! isset($parts['scheme'], $parts['host']) || $parts['host'] === ''
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || preg_match('/[\s\\\\]/', $url) === 1) {
            return false;
        }

        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host']);
        $loopback = in_array($host, self::LOOPBACK, true) || str_ends_with($host, '.localhost');

        return $scheme === 'https' || ($scheme === 'http' && $loopback);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && trim($value) !== '' && ! self::accepts($value)) {
            $fail('The Paymenter URL has to start with https:// (http:// only for localhost), with no user name, query string or fragment, such as https://billing.example.com.');
        }
    }
}
