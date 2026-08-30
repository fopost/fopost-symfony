<?php

declare(strict_types=1);

namespace Fopost\Symfony\Tests;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

abstract class TestCase extends KernelTestCase
{
    protected function tearDown(): void
    {
        self::ensureKernelShutdown();
        parent::tearDown();
    }

    /** @param array<string, mixed> $options */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel($options['fopost'] ?? ['api_key' => 'fp_test']);
    }

    protected function transport(): FakeTransport
    {
        $transport = self::getContainer()->get('fopost.tests.transport');
        self::assertInstanceOf(FakeTransport::class, $transport);

        return $transport;
    }
}
