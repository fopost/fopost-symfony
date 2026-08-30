<?php

declare(strict_types=1);

namespace Fopost\Symfony\Webhook;

use Fopost\Symfony\Event\FopostAccountHealthChangedEvent;
use Fopost\Symfony\Event\FopostDeliveryDelayedEvent;
use Fopost\Symfony\Event\FopostDeliveryFailedEvent;
use Fopost\Symfony\Event\FopostDeliveryPublishedEvent;
use Fopost\Symfony\Event\FopostPostFailedEvent;
use Fopost\Symfony\Event\FopostPostPartiallyFailedEvent;
use Fopost\Symfony\Event\FopostPostPublishedEvent;
use Fopost\Symfony\Event\FopostWebhookEvent;

/** The webhook events the API sends, and the event class each maps to. */
final class WebhookEvents
{
    public const POST_PUBLISHED = 'post.published';
    public const POST_FAILED = 'post.failed';
    public const POST_PARTIALLY_FAILED = 'post.partially_failed';
    public const DELIVERY_PUBLISHED = 'delivery.published';
    public const DELIVERY_FAILED = 'delivery.failed';
    public const DELIVERY_DELAYED = 'delivery.delayed';
    public const ACCOUNT_HEALTH_CHANGED = 'account.health_changed';

    /** @var array<string, class-string<FopostWebhookEvent>> */
    private const CLASSES = [
        self::POST_PUBLISHED => FopostPostPublishedEvent::class,
        self::POST_FAILED => FopostPostFailedEvent::class,
        self::POST_PARTIALLY_FAILED => FopostPostPartiallyFailedEvent::class,
        self::DELIVERY_PUBLISHED => FopostDeliveryPublishedEvent::class,
        self::DELIVERY_FAILED => FopostDeliveryFailedEvent::class,
        self::DELIVERY_DELAYED => FopostDeliveryDelayedEvent::class,
        self::ACCOUNT_HEALTH_CHANGED => FopostAccountHealthChangedEvent::class,
    ];

    /** @return array<int, string> */
    public static function all(): array
    {
        return array_keys(self::CLASSES);
    }

    /**
     * An event name the bundle does not know falls back to the base class, so
     * a new event added to the API still reaches listeners.
     *
     * @return class-string<FopostWebhookEvent>
     */
    public static function classFor(string $event): string
    {
        return self::CLASSES[$event] ?? FopostWebhookEvent::class;
    }
}
