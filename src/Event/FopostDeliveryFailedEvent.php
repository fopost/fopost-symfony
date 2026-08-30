<?php

declare(strict_types=1);

namespace Fopost\Symfony\Event;

/** One account delivery failed. Dispatched for the `delivery.failed` webhook. */
final class FopostDeliveryFailedEvent extends FopostWebhookEvent
{
}
