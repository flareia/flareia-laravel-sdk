<?php

namespace FlareIA\Sdk\Laravel\AttributesProviders;

use FlareIA\Sdk\Contracts\RequestAttributesProvider;
use FlareIA\Sdk\Support\PayloadCensor;
use Illuminate\Http\Request;

class LaravelRequestAttributesProvider implements RequestAttributesProvider
{
    public function __construct(
        private PayloadCensor $censor,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        if (! app()->bound('request')) {
            return [];
        }

        /** @var Request|null $request */
        $request = app('request');

        if (! $request instanceof Request) {
            return [];
        }

        $data = [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'headers' => $request->headers->all(),
        ];

        if ($request->hasSession()) {
            $data['session'] = $request->session()->all();
        }

        if ($request->cookies->count() > 0) {
            $data['cookies'] = $request->cookies->all();
        }

        return $this->censor->censorContext($data, config('flareia.censor', []));
    }
}
