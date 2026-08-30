<?php

declare(strict_types=1);

namespace Fopost\Symfony\Event;

/** A post failed on every account it targeted. Dispatched for the `post.failed` webhook. */
final class FopostPostFailedEvent extends FopostWebhookEvent
{
}
