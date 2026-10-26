<?php

declare(strict_types=1);

namespace KareliaLabs\PaymenterSso;

/**
 * Where to go after signing in: the login page's own "redirect" value (such as /server/1a2b3c4d), accepted only as a
 * path on this panel, the same rule the panel's login page applies. Anything else becomes "/", so the sign-in can
 * never send anyone to another site.
 */
final class LocalPath
{
    public static function from(mixed $value): string
    {
        if (! is_string($value) || strlen($value) > 2048
            || preg_match('#^/(?![/\\\\])[^\\\\\s\x00-\x1f\x7f]*$#D', $value) !== 1) {
            return '/';
        }

        $path = (string) parse_url('http://panel'.$value, PHP_URL_PATH);
        foreach (['/auth', '/extensions'] as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return '/';
            }
        }

        return $value;
    }
}
