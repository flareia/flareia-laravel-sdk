<?php

use FlareIA\Sdk\Laravel\Support\SecurityIdentifier;

it('masks email identifiers', function () {
    expect(SecurityIdentifier::mask('joao@example.com'))->toBe('j***@example.com');
});

it('hashes identifiers consistently', function () {
    $hash = SecurityIdentifier::hash('joao@example.com');
    expect($hash)->toHaveLength(64)
        ->and(SecurityIdentifier::hash('joao@example.com'))->toBe($hash);
});