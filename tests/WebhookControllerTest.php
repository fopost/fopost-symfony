<?php

declare(strict_types=1);

namespace Fopost\Symfony\Tests;

use Fopost\Symfony\Event\FopostPostPublishedEvent;
use Fopost\Symfony\Event\FopostWebhookEvent;
use Fopost\Symfony\Webhook\SignatureVerifier;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class WebhookControllerTest extends TestCase
{
    private const SECRET = 'whsec_test';

    public function testABadSignatureIsRejectedAndNothingIsDispatched(): void
    {
        $response = $this->deliver('{"event":"post.published","data":{"post_id":"p_1"}}', 'sha256=deadbeef');

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        $this->assertStringContainsString('invalid_signature', (string) $response->getContent());
        $this->assertSame([], $this->listener('any')->events);
    }

    public function testAMissingSignatureIsRejected(): void
    {
        $response = $this->deliver('{"event":"post.published","data":{}}', null);

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testAGoodSignatureDispatchesTheTypedEventAndTheBaseEvent(): void
    {
        $body = (string) json_encode([
            'event' => 'post.published',
            'data' => ['post_id' => 'p_1', 'workspace_id' => 'ws_1'],
            'timestamp' => '2026-08-30T10:00:00.000Z',
        ]);

        $response = $this->deliver($body, $this->sign($body), ['HTTP_X_FOPOST_DELIVERY' => 'wh_42']);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertStringContainsString('received', (string) $response->getContent());

        $typed = $this->listener('post_published')->events;
        $this->assertCount(1, $typed);
        $this->assertInstanceOf(FopostPostPublishedEvent::class, $typed[0]);
        $this->assertSame('post.published', $typed[0]->event);
        $this->assertSame('p_1', $typed[0]->get('post_id'));
        $this->assertSame('2026-08-30T10:00:00.000Z', $typed[0]->timestamp);
        $this->assertSame('wh_42', $typed[0]->deliveryId);

        $any = $this->listener('any')->events;
        $this->assertCount(1, $any);
        $this->assertSame($typed[0], $any[0]);
    }

    public function testTheSignatureCoversTheRawBodyByteForByte(): void
    {
        $body = '{"event":"post.published","data":{"post_id":"p_1"}}';
        $signature = $this->sign($body);

        // Same JSON, different bytes: the signature must not survive it.
        $response = $this->deliver('{"event":"post.published", "data":{"post_id":"p_1"}}', $signature);

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testAnUnknownEventStillReachesBaseListeners(): void
    {
        $body = (string) json_encode(['event' => 'post.something_new', 'data' => []]);

        $response = $this->deliver($body, $this->sign($body));

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $events = $this->listener('any')->events;
        $this->assertCount(1, $events);
        $this->assertSame(FopostWebhookEvent::class, $events[0]::class);
        $this->assertSame([], $this->listener('post_published')->events);
    }

    public function testABodyThatIsNotAnEventIsRejected(): void
    {
        $body = '{"nope":true}';

        $response = $this->deliver($body, $this->sign($body));

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    /** @param array<string, string> $server */
    private function deliver(string $body, ?string $signature, array $server = []): Response
    {
        self::bootKernel(['fopost' => ['api_key' => 'fp_test', 'webhook_secret' => self::SECRET]]);

        if ($signature !== null) {
            $server['HTTP_X_FOPOST_SIGNATURE'] = $signature;
        }
        $server['CONTENT_TYPE'] = 'application/json';

        $request = Request::create('/fopost/webhook', 'POST', [], [], [], $server, $body);

        return self::$kernel->handle($request);
    }

    private function sign(string $body): string
    {
        return (new SignatureVerifier(self::SECRET))->sign($body);
    }

    private function listener(string $which): RecordingListener
    {
        $listener = self::getContainer()->get("fopost.tests.listener.{$which}");
        $this->assertInstanceOf(RecordingListener::class, $listener);

        return $listener;
    }
}
