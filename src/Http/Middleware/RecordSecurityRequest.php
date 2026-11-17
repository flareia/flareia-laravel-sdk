<?php

namespace FlareIA\Sdk\Laravel\Http\Middleware;

use Closure;
use FlareIA\Sdk\Laravel\Security\SecurityPayloadBuffer;
use FlareIA\Sdk\Laravel\Support\ClientIp;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RecordSecurityRequest
{
    /** @var list<string> */
    protected const SENSITIVE_FRAGMENTS = [
        'horizon', 'telescope', 'pulse', '_ignition', 'log-viewer', 'filament', 'nova',
        '.env', 'wp-login', 'phpmyadmin',
    ];

    /** @var list<string> */
    protected const SCANNER_FRAGMENTS = [
        'sqlmap', 'nikto', 'nmap', 'masscan', 'acunetix', 'dirbuster',
    ];

    public function __construct(
        protected SecurityPayloadBuffer $buffer,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('flareia.security.enabled', true)) {
            return $next($request);
        }

        /** @var Response $response */
        $response = $next($request);

        $this->recordBucket($request, $response);

        return $response;
    }

    protected function recordBucket(Request $request, Response $response): void
    {
        $ip = ClientIp::fromRequest($request);
        if ($ip === null) {
            return;
        }

        $window = now()->startOfMinute()->toIso8601String();
        $status = $response->getStatusCode();
        $path = $this->normalizePath($request->path());
        $route = $request->route()?->getName() ?? $path;
        $uaFamily = $this->userAgentFamily($request->userAgent());
        $sensitive = $this->isSensitivePath($path);
        $scanner = $this->isScanner($request);

        $this->incrementBucket($window, 'ip', $ip, $request->method(), $route, $sensitive, $scanner);

        if ($uaFamily !== null) {
            $this->incrementBucket($window, 'user_agent', $uaFamily, $request->method(), $route, $sensitive, $scanner);
        }

        $this->incrementBucket($window, 'route', $route, $request->method(), $route, $sensitive, $scanner);

        $statusClass = (int) ($status / 100).'xx';
        $this->incrementBucket($window, 'status_class', $statusClass, $request->method(), $route, $sensitive, $scanner);

        if (in_array($status, [401, 403, 419, 429], true)) {
            $this->incrementBucket($window, 'status_exact', (string) $status, $request->method(), $route, $sensitive, $scanner);
        }

        if ($sensitive) {
            $this->incrementBucket($window, 'sensitive_route', $path, $request->method(), $route, true, $scanner);
        }

        if ($scanner) {
            $this->incrementBucket($window, 'scanner', $uaFamily ?? 'empty', $request->method(), $route, $sensitive, true);
        }
    }

    protected function incrementBucket(
        string $window,
        string $dimension,
        string $value,
        string $method,
        string $route,
        bool $sensitive,
        bool $scanner,
    ): void {
        $request = request();
        $ip = $request ? ClientIp::fromRequest($request) : null;

        $this->buffer->pushBucket([
            'window_start' => $window,
            'dimension' => $dimension,
            'value' => $value,
            'count' => 1,
            'method' => $method,
            'route' => $route,
            'ip' => $ip,
            'sensitive_route' => $sensitive,
            'scanner' => $scanner,
        ]);
    }

    protected function normalizePath(string $path): string
    {
        return preg_replace('/\d+/', '{id}', $path) ?: $path;
    }

    protected function userAgentFamily(?string $userAgent): ?string
    {
        if ($userAgent === null || $userAgent === '') {
            return 'empty';
        }

        $ua = strtolower($userAgent);

        return match (true) {
            str_contains($ua, 'curl') => 'curl',
            str_contains($ua, 'bot') => 'bot',
            str_contains($ua, 'chrome') => 'chrome',
            str_contains($ua, 'firefox') => 'firefox',
            str_contains($ua, 'safari') => 'safari',
            default => 'other',
        };
    }

    protected function isSensitivePath(string $path): bool
    {
        $lower = strtolower($path);

        foreach (self::SENSITIVE_FRAGMENTS as $fragment) {
            if (str_contains($lower, $fragment)) {
                return true;
            }
        }

        return false;
    }

    protected function isScanner(Request $request): bool
    {
        $ua = strtolower($request->userAgent() ?? '');

        foreach (self::SCANNER_FRAGMENTS as $fragment) {
            if (str_contains($ua, $fragment)) {
                return true;
            }
        }

        if ($ua === '' && $this->looksLikeAuthRoute($request)) {
            return true;
        }

        return false;
    }

    protected function looksLikeAuthRoute(Request $request): bool
    {
        $path = strtolower($request->path());

        return str_contains($path, 'login')
            || str_contains($path, 'password')
            || str_contains($path, 'two-factor');
    }
}