<?php

declare(strict_types=1);

namespace Fopost\Symfony\Event;

/** A post reached every account it targeted. Dispatched for the `post.published` webhook. */
final class FopostPostPublishedEvent extends FopostWebhookEvent
{
}
