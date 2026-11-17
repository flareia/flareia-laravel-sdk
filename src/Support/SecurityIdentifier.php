<?php

namespace FlareIA\Sdk\Laravel\Support;

class SecurityIdentifier
{
    public static function mask(?string $identifier): ?string
    {
        if ($identifier === null || $identifier === '') {
            return null;
        }

        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $parts = explode('@', $identifier, 2);
            $local = $parts[0];
            $domain = $parts[1] ?? '';
            $maskedLocal = strlen($local) <= 1
                ? '*'
                : $local[0].str_repeat('*', max(1, min(3, strlen($local) - 1)));

            return $maskedLocal.'@'.$domain;
        }

        if (strlen($identifier) <= 3) {
            return '***';
        }

        return substr($identifier, 0, 1).str_repeat('*', min(3, strlen($identifier) - 2)).substr($identifier, -1);
    }

    public static function hash(?string $identifier): ?string
    {
        if ($identifier === null || $identifier === '') {
            return null;
        }

        return hash('sha256', strtolower(trim($identifier)));
    }
}