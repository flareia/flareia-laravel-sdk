<?php

namespace FlareIA\Sdk\Laravel\Security;

class SecurityPayloadBuffer
{
    /** @var list<array<string, mixed>> */
    protected array $events = [];

    /** @var list<array<string, mixed>> */
    protected array $buckets = [];

    protected int $localAuthFailures = 0;

  /** @param  array<string, mixed>  $event */
    public function pushEvent(array $event): void
    {
        $this->events[] = $event;

        if (($event['type'] ?? '') === 'auth.failed') {
            $this->localAuthFailures++;
        }
    }

    /** @param  array<string, mixed>  $bucket */
    public function pushBucket(array $bucket): void
    {
        $this->buckets[] = $bucket;
    }

    public function localAuthFailures(): int
    {
        return $this->localAuthFailures;
    }

    public function resetLocalAuthFailures(): void
    {
        $this->localAuthFailures = 0;
    }

    public function isEmpty(): bool
    {
        return $this->events === [] && $this->buckets === [];
    }

    /**
     * @return array{events: list<array<string, mixed>>, buckets: list<array<string, mixed>>}
     */
    public function drain(): array
    {
        $payload = [
            'events' => $this->events,
            'buckets' => $this->buckets,
        ];

        $this->events = [];
        $this->buckets = [];

        return $payload;
    }
}