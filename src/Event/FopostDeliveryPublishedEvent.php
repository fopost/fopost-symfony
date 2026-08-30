<?php

declare(strict_types=1);

namespace Fopost\Symfony\Event;

/** One account delivery went live. Dispatched for the `delivery.published` webhook. */
final class FopostDeliveryPublishedEvent extends FopostWebhookEvent
{
}
