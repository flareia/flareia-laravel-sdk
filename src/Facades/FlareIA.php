<?php

namespace FlareIA\Sdk\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void report(\Throwable $exception, array $context = [], bool $handled = true)
 * @method static void reportMessage(string $message, string $level = 'error', array $context = [])
 * @method static \FlareIA\Sdk\Laravel\FlareIA context(string $key, mixed $value)
 *
 * @see \FlareIA\Sdk\Laravel\FlareIA
 */
class FlareIA extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \FlareIA\Sdk\Laravel\FlareIA::class;
    }
}
