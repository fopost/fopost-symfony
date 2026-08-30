<?php

declare(strict_types=1);

namespace Fopost\Symfony\Event;

/** One account delivery was pushed back and will be retried. Dispatched for the `delivery.delayed` webhook. */
final class FopostDeliveryDelayedEvent extends FopostWebhookEvent
{
}
