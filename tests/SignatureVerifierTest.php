<?php

declare(strict_types=1);

namespace Fopost\Symfony\Tests;

use Fopost\Symfony\Webhook\SignatureVerifier;
use LogicException;
use PHPUnit\Framework\TestCase as BaseTestCase;

final class SignatureVerifierTest extends BaseTestCase
{
    private const SECRET = 'whsec_test';
    private const BODY = '{"event":"post.published","data":{}}';

    public function testItMatchesTheDigestTheApiSends(): void
    {
        $verifier = new SignatureVerifier(self::SECRET);
        $expected = 'sha256=' . hash_hmac('sha256', self::BODY, self::SECRET);

        $this->assertSame($expected, $verifier->sign(self::BODY));
        $this->assertTrue($verifier->verify(self::BODY, $expected));
    }

    public function testABareHexDigestIsAcceptedToo(): void
    {
        $verifier = new SignatureVerifier(self::SECRET);

        $this->assertTrue($verifier->verify(self::BODY, hash_hmac('sha256', self::BODY, self::SECRET)));
    }

    public function testAnotherSecretDoesNotVerify(): void
    {
        $verifier = new SignatureVerifier(self::SECRET);

        $this->assertFalse($verifier->verify(self::BODY, (new SignatureVerifier('other'))->sign(self::BODY)));
    }

    public function testWithNoSecretEveryDeliveryIsRejected(): void
    {
        $verifier = new SignatureVerifier(null);

        $this->assertFalse($verifier->isConfigured());
        $this->assertFalse($verifier->verify(self::BODY, 'sha256=' . hash_hmac('sha256', self::BODY, '')));
    }

    public function testSigningWithoutASecretIsAProgrammingError(): void
    {
        $this->expectException(LogicException::class);

        (new SignatureVerifier(''))->sign(self::BODY);
    }
}
