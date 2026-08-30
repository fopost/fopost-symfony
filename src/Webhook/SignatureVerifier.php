<?php

declare(strict_types=1);

namespace Fopost\Symfony\Webhook;

use LogicException;

/**
 * Checks the HMAC-SHA256 signature FoPost puts on every webhook delivery.
 *
 * The API signs the raw request body with the webhook's secret and sends the
 * hex digest in the X-FoPost-Signature header, prefixed with "sha256=".
 */
final class SignatureVerifier
{
    public const SIGNATURE_HEADER = 'X-FoPost-Signature';
    public const EVENT_HEADER = 'X-FoPost-Event';
    public const DELIVERY_HEADER = 'X-FoPost-Delivery';

    private const ALGORITHM = 'sha256';
    private const PREFIX = 'sha256=';

    public function __construct(private readonly ?string $secret = null)
    {
    }

    public function isConfigured(): bool
    {
        return $this->secret !== null && $this->secret !== '';
    }

    /** The header value FoPost would send for this body. */
    public function sign(string $payload): string
    {
        if (!$this->isConfigured()) {
            throw new LogicException('fopost: set fopost.webhook_secret before signing or verifying a webhook');
        }

        return self::PREFIX . hash_hmac(self::ALGORITHM, $payload, (string) $this->secret);
    }

    /**
     * With no secret configured nothing verifies, so an unconfigured bundle
     * rejects every delivery rather than trusting it.
     */
    public function verify(string $payload, ?string $signature): bool
    {
        if (!$this->isConfigured() || $signature === null || $signature === '') {
            return false;
        }

        $expected = $this->sign($payload);

        return hash_equals($expected, $signature)
            || hash_equals(substr($expected, strlen(self::PREFIX)), $signature);
    }
}
