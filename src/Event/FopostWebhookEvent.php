<?php

declare(strict_types=1);

namespace Fopost\Symfony\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * A verified webhook delivery from FoPost.
 *
 * Every delivery is dispatched twice: once under the class that matches its
 * event name, and once under this base class, so a listener can either narrow
 * to one event or catch the lot.
 */
class FopostWebhookEvent extends Event
{
    /**
     * @param array<string, mixed> $data The event body, the `data` key of the payload.
     * @param array<string, mixed> $payload The whole decoded payload.
     */
    public function __construct(
        public readonly string $event,
        public readonly array $data,
        public readonly ?string $timestamp = null,
        public readonly array $payload = [],
        public readonly ?string $deliveryId = null,
    ) {
    }

    public function get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }
}
