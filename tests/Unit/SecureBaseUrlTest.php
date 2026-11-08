<?php

use FlareIA\Sdk\Support\SecureBaseUrl;

it('accepts the default production base URL', function (): void {
    expect(SecureBaseUrl::validate('https://flareia.com.br'))
        ->toBe('https://flareia.com.br');
});

it('rejects HTTP for non-local hosts', function (): void {
    SecureBaseUrl::validate('http://flareia.com.br');
})->throws(InvalidArgumentException::class);

it('allows HTTP for local daemon URLs', function (): void {
    expect(SecureBaseUrl::validate('http://127.0.0.1:8787'))
        ->toBe('http://127.0.0.1:8787');
});

it('blocks cloud metadata hosts', function (): void {
    SecureBaseUrl::validate('https://169.254.169.254/latest/meta-data/');
})->throws(InvalidArgumentException::class);
