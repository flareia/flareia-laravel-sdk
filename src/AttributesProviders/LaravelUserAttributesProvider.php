<?php

namespace FlareIA\Sdk\Laravel\AttributesProviders;

use FlareIA\Sdk\Contracts\UserAttributesProvider;

class LaravelUserAttributesProvider implements UserAttributesProvider
{
    /**
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        if (! app()->bound('request')) {
            return [];
        }

        $user = app('request')->user();

        if ($user === null) {
            return [];
        }

        return array_filter([
            'id' => $user->getAuthIdentifier(),
            'email' => $user->email ?? null,
        ]);
    }
}
