<?php

declare(strict_types=1);

namespace Fopost\Symfony\Event;

/** A post reached some accounts and failed on others. Dispatched for the `post.partially_failed` webhook. */
final class FopostPostPartiallyFailedEvent extends FopostWebhookEvent
{
}
