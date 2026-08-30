<?php

declare(strict_types=1);

namespace Fopost\Symfony\Tests;

use Fopost\Sdk\Http\Response;
use Fopost\Sdk\Http\Transport;
use RuntimeException;

/** Stands in for the wire: records what the SDK sent, replays queued bodies. */
final class FakeTransport implements Transport
{
    /** @var array<int, Response> */
    private array $queue = [];

    /** @var array<int, array{method: string, url: string, headers: array<string, string>, body: ?string}> */
    public array $requests = [];

    /** @param array<string, string> $headers */
    public function push(int $status, mixed $body = null, array $headers = []): self
    {
        $encoded = is_string($body) || $body === null ? ($body ?? '') : (string) json_encode($body);
        $this->queue[] = new Response($status, $headers, $encoded);

        return $this;
    }

    public function send(string $method, string $url, array $headers, ?string $body): Response
    {
        $this->requests[] = ['method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $body];

        return array_shift($this->queue) ?? new Response(200, [], '{"data":{}}');
    }

    /** @return array{method: string, url: string, headers: array<string, string>, body: ?string} */
    public function last(): array
    {
        $request = end($this->requests);
        if ($request === false) {
            throw new RuntimeException('no request was sent');
        }

        return $request;
    }

    /** @return array<string, mixed> */
    public function lastJson(): array
    {
        $body = $this->last()['body'];

        return is_string($body) ? (array) json_decode($body, true) : [];
    }

    public function reset(): void
    {
        $this->queue = [];
        $this->requests = [];
    }
}
