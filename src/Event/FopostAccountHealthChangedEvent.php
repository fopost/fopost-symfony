<?php

declare(strict_types=1);

namespace Fopost\Symfony\Event;

/** A connected account changed health, usually an expired token. Dispatched for the `account.health_changed` webhook. */
final class FopostAccountHealthChangedEvent extends FopostWebhookEvent
{
}
