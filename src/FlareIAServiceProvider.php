<?php

namespace FlareIA\Sdk\Laravel;

use FlareIA\Sdk\Api;
use FlareIA\Sdk\Laravel\AttributesProviders\LaravelRequestAttributesProvider;
use FlareIA\Sdk\Laravel\AttributesProviders\LaravelUserAttributesProvider;
use FlareIA\Sdk\Laravel\Console\SendTestEventCommand;
use FlareIA\Sdk\Laravel\Recorders\JobRecorder;
use FlareIA\Sdk\Laravel\Recorders\QueryRecorder;
use FlareIA\Sdk\Laravel\Senders\LaravelHttpSender;
use FlareIA\Sdk\Laravel\Senders\LaravelVaporSender;
use FlareIA\Sdk\Resource;
use FlareIA\Sdk\Sampling\RateSampler;
use FlareIA\Sdk\Scope;
use FlareIA\Sdk\Senders\Sender;
use FlareIA\Sdk\Support\PayloadCensor;
use FlareIA\Sdk\Support\SecureBaseUrl;
use FlareIA\Sdk\Support\StacktraceBuilder;
use FlareIA\Sdk\Tracer;
use Illuminate\Support\ServiceProvider;

class FlareIAServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/flareia.php', 'flareia');

        $this->app->singleton(Sender::class, function () {
            return $this->makeConfiguredSender();
        });

        $this->app->singleton(Api::class, function ($app) {
            return new Api(
                sender: $app->make(Sender::class),
                baseUrl: SecureBaseUrl::validate((string) config('flareia.base_url')),
                apiKey: (string) config('flareia.api_key'),
            );
        });

        $this->app->singleton(StacktraceBuilder::class, function () {
            return new StacktraceBuilder(
                applicationPath: (string) config('flareia.base_path', base_path()),
                collectArguments: (bool) config('flareia.collect_arguments', false),
            );
        });

        $this->app->singleton(PayloadCensor::class);
        $this->app->singleton(RateSampler::class);
        $this->app->singleton(LaravelRequestAttributesProvider::class);
        $this->app->singleton(LaravelUserAttributesProvider::class);

        $this->app->singleton(Resource::class, function () {
            return Resource::make(
                environment: config('flareia.environment'),
                release: config('flareia.release'),
                sdkLanguage: 'php',
            );
        });

        $this->app->singleton(Scope::class, function () {
            return new Scope('flareia-laravel');
        });

        $this->app->singleton(Tracer::class);

        $this->app->singleton(FlareIA::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/flareia.php' => config_path('flareia.php'),
            ], 'flareia-config');

            $this->commands([
                SendTestEventCommand::class,
            ]);
        }

        if (! config('flareia.trace', true)) {
            return;
        }

        if (config('flareia.recorders.query', true)) {
            $this->app->make(QueryRecorder::class)->register();
        }

        if (config('flareia.recorders.job', true)) {
            $this->app->make(JobRecorder::class)->register();
        }

        $this->app->terminating(function () {
            $this->app->make(Tracer::class)->flush();
        });
    }

    protected function makeConfiguredSender(): Sender
    {
        $class = (string) config('flareia.sender.class', LaravelHttpSender::class);
        /** @var array<string, mixed> $config */
        $config = config('flareia.sender.config', []);
        $config['timeout'] ??= (int) config('flareia.timeout', 2);

        if ($class === LaravelVaporSender::class || is_a($class, LaravelVaporSender::class, true)) {
            return new $class($config);
        }

        if ($class === LaravelHttpSender::class || is_a($class, LaravelHttpSender::class, true)) {
            return new LaravelHttpSender((int) $config['timeout']);
        }

        return new $class($config);
    }
}
