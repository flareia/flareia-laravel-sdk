<?php

namespace FlareIA\Sdk\Laravel;

use FlareIA\Sdk\Api;
use FlareIA\Sdk\Laravel\AttributesProviders\LaravelRequestAttributesProvider;
use FlareIA\Sdk\Laravel\AttributesProviders\LaravelUserAttributesProvider;
use FlareIA\Sdk\Laravel\Jobs\SendErrorReport;
use FlareIA\Sdk\Laravel\Senders\LaravelHttpSender;
use FlareIA\Sdk\Laravel\Senders\LaravelVaporSender;
use FlareIA\Sdk\Sampling\RateSampler;
use FlareIA\Sdk\Support\PayloadCensor;
use FlareIA\Sdk\Support\StacktraceBuilder;
use Illuminate\Foundation\Configuration\Exceptions;
use Throwable;

class FlareIA
{
    /** @var array<string, mixed> */
    protected array $context = [];

    public function __construct(
        protected StacktraceBuilder $stacktraceBuilder,
        protected PayloadCensor $censor,
        protected RateSampler $sampler,
        protected Api $api,
        protected LaravelRequestAttributesProvider $requestAttributesProvider,
        protected LaravelUserAttributesProvider $userAttributesProvider,
    ) {}

    public function context(string $key, mixed $value): static
    {
        $this->context[$key] = $value;

        return $this;
    }

    public function report(Throwable $exception, array $context = [], bool $handled = true): void
    {
        if (! config('flareia.enabled', true)) {
            return;
        }

        foreach (config('flareia.ignore_exceptions', []) as $ignored) {
            if ($exception instanceof $ignored) {
                return;
            }
        }

        if (! $this->sampler->shouldSample((float) config('flareia.sample_rate', 1.0))) {
            return;
        }

        $payload = [
            'exception' => [
                'class' => get_class($exception),
                'message' => $exception->getMessage(),
            ],
            'stacktrace' => $this->stacktraceBuilder->build($exception),
            'previous' => $this->stacktraceBuilder->buildChain($exception),
            'context' => $this->censor->censorFields(
                array_merge($this->context, $context),
                config('flareia.censor.body_fields', []),
            ),
            'request' => $this->buildRequestContext(),
            'environment' => config('flareia.environment'),
            'release' => config('flareia.release'),
            'server_name' => gethostname() ?: null,
            'handled' => $handled,
            'level' => 'error',
            'occurred_at' => now()->toIso8601String(),
        ];

        $this->send($payload);
    }

    public function reportMessage(string $message, string $level = 'error', array $context = []): void
    {
        if (! config('flareia.enabled', true)) {
            return;
        }

        if (! $this->sampler->shouldSample((float) config('flareia.sample_rate', 1.0))) {
            return;
        }

        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        $caller = $trace[1] ?? $trace[0] ?? [];

        $payload = [
            'exception' => [
                'class' => 'Message',
                'message' => $message,
            ],
            'stacktrace' => [[
                'file' => $caller['file'] ?? '',
                'line' => $caller['line'] ?? null,
                'function' => $caller['function'] ?? null,
                'is_application_frame' => true,
            ]],
            'context' => $this->censor->censorFields(
                array_merge($this->context, $context),
                config('flareia.censor.body_fields', []),
            ),
            'request' => $this->buildRequestContext(),
            'environment' => config('flareia.environment'),
            'release' => config('flareia.release'),
            'server_name' => gethostname() ?: null,
            'handled' => true,
            'level' => $level,
            'occurred_at' => now()->toIso8601String(),
        ];

        $this->send($payload);
    }

    public static function handles(Exceptions $exceptions): void
    {
        $exceptions->reportable(function (Throwable $e) {
            // Anything reaching this global hook propagated all the way up
            // without being caught by application code — by definition
            // unhandled, as opposed to a developer's explicit ->report() call.
            app(self::class)->report($e, handled: false);
        });
    }

    protected function send(array $payload): void
    {
        // LaravelVaporSender owns queue-vs-sync per entity type — do not also
        // wrap errors here or payloads get double-queued.
        if ($this->usesLegacyHttpQueue()) {
            SendErrorReport::dispatch($payload)->onQueue(config('flareia.queue', 'default'));

            return;
        }

        $this->api->sendError($payload);
    }

    protected function usesLegacyHttpQueue(): bool
    {
        if (! config('flareia.async_via_queue', true)) {
            return false;
        }

        $sender = (string) config('flareia.sender.class', LaravelHttpSender::class);

        // Vapor sender already decides queue vs sync per entity type.
        return ! is_a($sender, LaravelVaporSender::class, true);
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildRequestContext(): array
    {
        $data = $this->requestAttributesProvider->attributes();

        if ($data === []) {
            return [];
        }

        $user = $this->userAttributesProvider->attributes();

        if ($user !== []) {
            $data['user'] = $user;
        }

        return $data;
    }
}
