<?php

namespace FlareIA\Sdk\Laravel\Support;

use Illuminate\Http\Request;

class ClientIp
{
    public static function normalize(?string $ip): ?string
    {
        if ($ip === null || $ip === '') {
            return null;
        }

        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return $ip;
        }

        $packed = inet_pton($ip);
        if ($packed === false || strlen($packed) !== 16) {
            return $ip;
        }

        $prefix = substr($packed, 0, 8);
        $suffix = inet_ntop($prefix.str_repeat("\x00", 8));

        return $suffix !== false ? $suffix : $ip;
    }

    public static function fromRequest(?Request $request = null): ?string
    {
        $request ??= request();
        if ($request === null) {
            return null;
        }

        return self::normalize($request->ip());
    }
}