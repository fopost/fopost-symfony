<?php

declare(strict_types=1);

namespace Fopost\Symfony\Tests;

use Fopost\Sdk\Client;
use Fopost\Symfony\Command\AccountsCommand;
use Fopost\Symfony\Command\PostCommand;
use Fopost\Symfony\Controller\WebhookController;
use Fopost\Symfony\Webhook\SignatureVerifier;

final class BundleTest extends TestCase
{
    public function testTheBundleLoadsAndTheClientIsAvailableForAutowiring(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $client = $container->get(Client::class);

        $this->assertInstanceOf(Client::class, $client);
        $this->assertSame($client, $container->get('fopost.client'));
    }

    public function testTheClientIsBuiltFromTheBundleConfiguration(): void
    {
        self::bootKernel(['fopost' => [
            'api_key' => 'fp_from_config',
            'base_url' => 'https://api.example.test',
            'timeout' => 5.0,
            'max_retries' => 2,
        ]]);

        $client = self::getContainer()->get(Client::class);
        $this->assertInstanceOf(Client::class, $client);
        $this->assertSame('https://api.example.test/api/v1', $client->baseUrl());

        $this->transport()->push(200, ['data' => []]);
        $client->workspaces()->list();

        $headers = $this->transport()->last()['headers'];
        $this->assertSame('fp_from_config', $headers['X-API-Key']);
        $this->assertSame('https://api.example.test/api/v1/workspaces', $this->transport()->last()['url']);
    }

    public function testEveryConfigurationKeyBecomesAContainerParameter(): void
    {
        self::bootKernel(['fopost' => [
            'api_key' => 'fp_params',
            'default_workspace_id' => 'ws_1',
            'webhook_secret' => 'whsec',
        ]]);

        $container = self::getContainer();
        $this->assertSame('fp_params', $container->getParameter('fopost.api_key'));
        $this->assertSame(Client::DEFAULT_BASE_URL, $container->getParameter('fopost.base_url'));
        $this->assertSame(30.0, $container->getParameter('fopost.timeout'));
        $this->assertSame(3, $container->getParameter('fopost.max_retries'));
        $this->assertSame('ws_1', $container->getParameter('fopost.default_workspace_id'));
        $this->assertSame('whsec', $container->getParameter('fopost.webhook_secret'));
    }

    public function testTheWebhookControllerCommandsAndVerifierAreRegistered(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->assertInstanceOf(WebhookController::class, $container->get(WebhookController::class));
        $this->assertInstanceOf(SignatureVerifier::class, $container->get(SignatureVerifier::class));
        $this->assertInstanceOf(PostCommand::class, $container->get(PostCommand::class));
        $this->assertInstanceOf(AccountsCommand::class, $container->get(AccountsCommand::class));
    }

    public function testTheBundleRouteIsImportable(): void
    {
        self::bootKernel();
        $router = self::getContainer()->get('router');
        $this->assertNotNull($router);

        $route = $router->getRouteCollection()->get('fopost_webhook');
        $this->assertNotNull($route);
        $this->assertSame('/fopost/webhook', $route->getPath());
        $this->assertSame(WebhookController::class, $route->getDefault('_controller'));
    }
}
