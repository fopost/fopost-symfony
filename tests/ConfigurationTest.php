<?php

declare(strict_types=1);

namespace Fopost\Symfony\Tests;

use Fopost\Sdk\Client;
use Fopost\Sdk\Http\HttpClient;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

final class ConfigurationTest extends TestCase
{
    public function testAMissingApiKeyIsRejected(): void
    {
        $kernel = new TestKernel([]);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/api_key/');

        $kernel->boot();
    }

    public function testAnEmptyApiKeyIsRejected(): void
    {
        $kernel = new TestKernel(['api_key' => '']);

        $this->expectException(InvalidConfigurationException::class);

        $kernel->boot();
    }

    public function testEnvPlaceholdersResolveAtRuntime(): void
    {
        $_ENV['FOPOST_TEST_API_KEY'] = 'fp_from_env';
        $_ENV['FOPOST_TEST_BASE_URL'] = 'https://env.example.test';
        $_ENV['FOPOST_TEST_MAX_RETRIES'] = '2';
        $_ENV['FOPOST_TEST_TIMEOUT'] = '7.5';

        self::bootKernel(['fopost' => [
            'api_key' => '%env(FOPOST_TEST_API_KEY)%',
            'base_url' => '%env(FOPOST_TEST_BASE_URL)%',
            'timeout' => '%env(float:FOPOST_TEST_TIMEOUT)%',
            'max_retries' => '%env(int:FOPOST_TEST_MAX_RETRIES)%',
        ]]);

        $container = self::getContainer();
        $this->assertSame(7.5, $container->getParameter('fopost.timeout'));
        $this->assertSame(2, $container->getParameter('fopost.max_retries'));

        $client = $container->get(Client::class);
        $this->assertInstanceOf(Client::class, $client);
        $this->assertSame('https://env.example.test' . HttpClient::API_PATH_SUFFIX, $client->baseUrl());

        $this->transport()->push(200, ['data' => []]);
        $client->workspaces()->list();

        $this->assertSame('fp_from_env', $this->transport()->last()['headers']['X-API-Key']);
    }
}
