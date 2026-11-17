<?php

use FlareIA\Sdk\Laravel\Recorders\SecurityRecorder;
use FlareIA\Sdk\Laravel\Security\SecurityPayloadBuffer;
use FlareIA\Sdk\Laravel\Security\SecurityTransmitter;
use FlareIA\Sdk\Laravel\Tests\TestCase;
use FlareIA\Sdk\Support\PayloadCensor;
use Illuminate\Auth\Events\Failed;
use Illuminate\Http\Request;

uses(TestCase::class);

it('never includes password in security payload on failed login', function () {
    $captured = [];
    $transmitter = \Mockery::mock(SecurityTransmitter::class);
    $transmitter->shouldReceive('send')->andReturnUsing(function (array $payload) use (&$captured) {
        $captured = $payload;
    });

    config(['flareia.security.enabled' => true]);

    $request = Request::create('/login', 'POST', [
        'email' => 'attacker@evil.com',
        'password' => 'super-secret',
    ]);
    $this->app->instance('request', $request);

    $recorder = new SecurityRecorder(
        new SecurityPayloadBuffer,
        $transmitter,
        new PayloadCensor,
    );

    $recorder->register($this->app['events']);
    $this->app['events']->dispatch(new Failed('web', null, [
        'email' => 'attacker@evil.com',
        'password' => 'super-secret',
    ]));

    expect($captured)->not->toBeEmpty();
    $json = json_encode($captured);
    expect($json)->not->toContain('super-secret')
        ->and($captured['events'][0]['login_field'])->toBe('email')
        ->and($captured['events'][0]['identifier_mask'])->toBe('a***@evil.com');
});