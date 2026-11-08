<?php

namespace FlareIA\Sdk\Laravel\Support;

class Configuration
{
    public static function envBoolean(string $key, bool $default): bool
    {
        return self::toBoolean(env($key), $default);
    }

    public static function toBoolean(mixed $value, bool $default): bool
    {
        if ($value === null) {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $parsed ?? $default;
    }
}
