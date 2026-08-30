<?php

declare(strict_types=1);

namespace Fopost\Symfony\Tests;

use Fopost\Symfony\Event\FopostWebhookEvent;

/** Collects every webhook event the bundle dispatches during a test. */
final class RecordingListener
{
    /** @var array<int, FopostWebhookEvent> */
    public array $events = [];

    public function __invoke(FopostWebhookEvent $event): void
    {
        $this->events[] = $event;
    }

    public function reset(): void
    {
        $this->events = [];
    }
}
