<?php

declare(strict_types=1);

namespace Fopost\Symfony\Controller;

use Fopost\Symfony\Event\FopostWebhookEvent;
use Fopost\Symfony\Webhook\SignatureVerifier;
use Fopost\Symfony\Webhook\WebhookEvents;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Receives webhook deliveries from FoPost.
 *
 * The signature covers the raw body, so it is read before anything decodes or
 * normalises it. A verified delivery becomes a Symfony event; listeners do the
 * work, and this returns 200 straight away so the API is not kept waiting.
 */
final class WebhookController
{
    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
        private readonly SignatureVerifier $verifier,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $body = $request->getContent();

        if (!$this->verifier->verify($body, $request->headers->get(SignatureVerifier::SIGNATURE_HEADER))) {
            $this->logger?->warning('fopost: rejected a webhook delivery with an invalid signature');

            return new JsonResponse(['error' => 'invalid_signature'], Response::HTTP_UNAUTHORIZED);
        }

        $payload = json_decode($body, true);
        if (!is_array($payload)) {
            return new JsonResponse(['error' => 'invalid_payload'], Response::HTTP_BAD_REQUEST);
        }

        $name = $payload['event'] ?? $request->headers->get(SignatureVerifier::EVENT_HEADER);
        if (!is_string($name) || $name === '') {
            return new JsonResponse(['error' => 'invalid_payload'], Response::HTTP_BAD_REQUEST);
        }

        $class = WebhookEvents::classFor($name);
        $event = new $class(
            $name,
            is_array($payload['data'] ?? null) ? $payload['data'] : [],
            is_string($payload['timestamp'] ?? null) ? $payload['timestamp'] : null,
            $payload,
            $request->headers->get(SignatureVerifier::DELIVERY_HEADER),
        );

        $this->dispatcher->dispatch($event, $class);
        if ($class !== FopostWebhookEvent::class) {
            $this->dispatcher->dispatch($event, FopostWebhookEvent::class);
        }

        return new JsonResponse(['received' => true]);
    }
}
