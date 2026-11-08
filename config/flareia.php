<?php

use FlareIA\Sdk\Laravel\Senders\LaravelHttpSender;
use FlareIA\Sdk\Laravel\Support\Configuration;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return [
    'api_key' => env('FLAREIA_API_KEY'),

    'base_url' => env('FLAREIA_BASE_URL', 'https://flareia.com.br'),

    'enabled' => env('FLAREIA_ENABLED', true),

    'environment' => env('FLAREIA_ENVIRONMENT', env('APP_ENV', 'production')),

    'release' => env('FLAREIA_RELEASE'),

    'sample_rate' => (float) env('FLAREIA_SAMPLE_RATE', 1.0),

    /*
    | Legacy toggle used when sender.class is LaravelHttpSender. Prefer the
    | Vapor/Bref sender on Lambda — it queues traces/logs and keeps errors
    | sync by default.
    */
    'async_via_queue' => env('FLAREIA_ASYNC_VIA_QUEUE', true),

    'queue' => env('FLAREIA_QUEUE', 'default'),

    'timeout' => (int) env('FLAREIA_TIMEOUT', 2),

    /*
    |--------------------------------------------------------------------------
    | Sender
    |--------------------------------------------------------------------------
    |
    | LaravelHttpSender posts immediately (or FlareIA::send may still wrap
    | errors with async_via_queue). On AWS Lambda (Laravel Vapor or Bref /
    | Serverless) use one of:
    |
    |   'class' => \FlareIA\Sdk\Laravel\Senders\LaravelVaporSender::class,
    |   // or
    |   'class' => \FlareIA\Sdk\Laravel\Senders\LaravelBrefSender::class,
    |
    |   'config' => [
    |       'queue_errors' => false,
    |       'queue_traces' => true,
    |       'queue_logs' => true,
    |       'connection' => 'sqs',
    |       'queue' => 'flareia',
    |   ],
    |
    | Set async_via_queue to false so errors are not queued twice.
    |
    */
    'sender' => [
        'class' => env('FLAREIA_SENDER', LaravelHttpSender::class),
        'config' => [
            'timeout' => (int) env('FLAREIA_TIMEOUT', 2),
            'queue_errors' => Configuration::envBoolean('FLAREIA_QUEUE_ERRORS', false),
            'queue_traces' => Configuration::envBoolean('FLAREIA_QUEUE_TRACES', true),
            'queue_logs' => Configuration::envBoolean('FLAREIA_QUEUE_LOGS', true),
            'connection' => env('FLAREIA_QUEUE_CONNECTION'),
            'queue' => env('FLAREIA_SENDER_QUEUE', env('FLAREIA_QUEUE', 'default')),
        ],
    ],

    'base_path' => env('FLAREIA_BASE_PATH', base_path()),

    'collect_arguments' => env('FLAREIA_COLLECT_ARGUMENTS', false),

    'trace' => env('FLAREIA_TRACE', true),

    'recorders' => [
        'query' => env('FLAREIA_RECORD_QUERIES', true),
        'job' => env('FLAREIA_RECORD_JOBS', true),
    ],

    'censor' => [
        'body_fields' => ['password', 'password_confirmation', 'token', 'secret', 'api_key'],
        'headers' => ['authorization', 'cookie', 'set-cookie', 'x-csrf-token', 'x-xsrf-token'],
        'cookies' => false,
        'session' => false,
        'client_ips' => false,
    ],

    'ignore_exceptions' => [
        AuthenticationException::class,
        ValidationException::class,
        NotFoundHttpException::class,
    ],
];
