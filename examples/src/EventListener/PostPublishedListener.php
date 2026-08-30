<?php

declare(strict_types=1);

namespace App\EventListener;

use Fopost\Symfony\Event\FopostPostFailedEvent;
use Fopost\Symfony\Event\FopostPostPublishedEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * The bundle verifies the signature and turns each delivery into an event, so
 * a listener is all an application writes.
 */
final class PostPublishedListener
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    #[AsEventListener]
    public function onPublished(FopostPostPublishedEvent $event): void
    {
        $this->logger->info('post live', ['post' => $event->get('post_id'), 'at' => $event->timestamp]);
    }

    #[AsEventListener]
    public function onFailed(FopostPostFailedEvent $event): void
    {
        $this->logger->error('post failed', ['post' => $event->get('post_id'), 'data' => $event->data]);
    }
}
