<?php

namespace FlareIA\Sdk\Laravel\Http\Middleware;

use Closure;
use FlareIA\Sdk\Laravel\Recorders\SecurityRecorder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHoneypot
{
    /** @var list<string> */
    protected const DEFAULT_PATHS = [
        'wp-login.php',
        'phpmyadmin',
        '.env',
    ];

    public function __construct(
        protected SecurityRecorder $recorder,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('flareia.security.honeypot', false)) {
            return $next($request);
        }

        $path = trim($request->path(), '/');

        foreach ($this->paths() as $honeypot) {
            $needle = trim($honeypot, '/');
            if ($path === $needle || str_ends_with('/'.$path, '/'.$needle)) {
                $this->recorder->recordCustom('honeypot_hit', [
                    'path' => '/'.$path,
                ]);

                return response('', Response::HTTP_NOT_FOUND);
            }
        }

        return $next($request);
    }

    /**
     * @return list<string>
     */
    protected function paths(): array
    {
        $configured = config('flareia.security.honeypot_paths');

        return is_array($configured) && $configured !== []
            ? array_values(array_map('strval', $configured))
            : self::DEFAULT_PATHS;
    }
}