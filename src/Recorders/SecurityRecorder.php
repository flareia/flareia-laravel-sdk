<?php

namespace FlareIA\Sdk\Laravel\Recorders;

use FlareIA\Sdk\Laravel\Security\SecurityPayloadBuffer;
use FlareIA\Sdk\Laravel\Security\SecurityTransmitter;
use FlareIA\Sdk\Laravel\Support\ClientIp;
use FlareIA\Sdk\Laravel\Support\SecurityIdentifier;
use FlareIA\Sdk\Support\PayloadCensor;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;

class SecurityRecorder
{
    /** @var list<class-string> */
    protected array $optionalListeners = [];

    public function __construct(
        protected SecurityPayloadBuffer $buffer,
        protected SecurityTransmitter $transmitter,
        protected PayloadCensor $censor,
    ) {}

    public function register(Dispatcher $events): void
    {
        $events->listen(Failed::class, function (Failed $e): void {
            $credentials = is_array($e->credentials) ? $e->credentials : [];
            [$identifier, $loginField] = $this->loginIdentifierFromCredentials($credentials);

            if ($identifier === null) {
                $request = request();
                if ($request !== null) {
                    [$identifier, $loginField] = $this->loginIdentifierFromCredentials(
                        $request->only(['email', 'username', 'login']),
                    );
                }
            }

            $this->recordAuthEvent('auth.failed', is_string($identifier) ? $identifier : null, $loginField);
        });
        $events->listen(Login::class, fn () => $this->recordAuthEvent('auth.login', $this->identifierFromRequest()));
        $events->listen(Logout::class, fn () => $this->recordAuthEvent('auth.logout', $this->identifierFromRequest()));
        $events->listen(Lockout::class, fn () => $this->recordAuthEvent('auth.lockout', $this->identifierFromRequest()));
        $events->listen(PasswordReset::class, fn (PasswordReset $e) => $this->recordAuthEvent('auth.password_reset', $e->user->getEmailForPasswordReset() ?? null));
        $events->listen(Registered::class, fn (Registered $e) => $this->recordAuthEvent('auth.registered', $e->user->getEmail() ?? (string) $e->user->getAuthIdentifier()));

        foreach ([
            'Laravel\Fortify\Events\TwoFactorAuthenticationFailed',
            'Illuminate\Auth\Events\CurrentDeviceLogout',
            'Illuminate\Auth\Events\OtherDeviceLogout',
        ] as $class) {
            if (class_exists($class)) {
                $events->listen($class, fn () => $this->recordAuthEvent($this->typeFromClass($class), $this->identifierFromRequest()));
            }
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    /**
     * @param  array<string, mixed>  $credentials
     * @return array{0: ?string, 1: ?string}
     */
    protected function loginIdentifierFromCredentials(array $credentials): array
    {
        foreach (['email', 'username', 'login'] as $key) {
            $value = $credentials[$key] ?? null;
            if (is_string($value) && $value !== '') {
                return [$value, $key];
            }
        }

        return [null, null];
    }

    public function recordCustom(string $type, array $context = []): void
    {
        if (! config('flareia.security.enabled', true)) {
            return;
        }

        $context = $this->censor->censorFields($context, config('flareia.censor.body_fields', []));

        unset($context['password'], $context['password_confirmation'], $context['token'], $context['secret']);
        $identifier = isset($context['identifier']) && is_string($context['identifier'])
            ? $context['identifier']
            : null;

        $loginField = isset($context['login_field']) && is_string($context['login_field'])
            ? $context['login_field']
            : null;

        $event = $this->buildEvent($type, $identifier, $loginField);
        $this->transmitter->send(['events' => [$event], 'buckets' => []]);
    }

    protected function recordAuthEvent(string $type, ?string $identifier, ?string $loginField = null): void
    {
        if (! config('flareia.security.enabled', true)) {
            return;
        }

        $event = $this->buildEvent($type, $identifier, $loginField);
        $this->buffer->pushEvent($event);
        $payload = $this->buffer->drain();
        $payload['events'] = [$event];
        $this->transmitter->send($payload);
    }

    protected function buildEvent(string $type, ?string $identifier, ?string $loginField = null): array
    {
        $request = request();
        $route = $request?->route();
        $routeName = $route?->getName();

        if ($loginField === null && $request !== null) {
            [, $loginField] = $this->loginIdentifierFromCredentials($request->only(['email', 'username', 'login']));
        }

        return [
            'type' => $type,
            'occurred_at' => now()->toIso8601String(),
            'ip' => ClientIp::fromRequest($request),
            'user_agent' => $this->shortUserAgent($request),
            'route' => $routeName ?? $this->normalizePath($request?->path()),
            'method' => $request?->method(),
            'identifier_hash' => SecurityIdentifier::hash($identifier),
            'identifier_mask' => SecurityIdentifier::mask($identifier),
            'login_field' => $loginField,
        ];
    }

    protected function identifierFromRequest(): ?string
    {
        $request = request();
        if ($request === null) {
            return null;
        }

        [$identifier] = $this->loginIdentifierFromCredentials($request->only(['email', 'username', 'login']));
        if (is_string($identifier)) {
            return $identifier;
        }

        return $request->user()?->getAuthIdentifier();
    }

    protected function shortUserAgent(?Request $request): ?string
    {
        $ua = $request?->userAgent();
        if ($ua === null) {
            return null;
        }

        return strlen($ua) > 200 ? substr($ua, 0, 200) : $ua;
    }

    protected function normalizePath(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        return preg_replace('/\d+/', '{id}', $path) ?: $path;
    }

    protected function typeFromClass(string $class): string
    {
        return match ($class) {
            'Laravel\Fortify\Events\TwoFactorAuthenticationFailed' => 'auth.two_factor_failed',
            'Illuminate\Auth\Events\CurrentDeviceLogout' => 'auth.logout_current_device',
            'Illuminate\Auth\Events\OtherDeviceLogout' => 'auth.logout_other_device',
            default => 'auth.custom',
        };
    }
}