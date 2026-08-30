<?php

declare(strict_types=1);

namespace Fopost\Symfony;

use Fopost\Sdk\Client;
use Fopost\Symfony\Command\AccountsCommand;
use Fopost\Symfony\Command\PostCommand;
use Fopost\Symfony\Controller\WebhookController;
use Fopost\Symfony\Webhook\SignatureVerifier;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;

/**
 * Wires the FoPost Cloud API into Symfony.
 *
 * Everything the API does lives in fopost/sdk. This bundle binds one client
 * from your configuration, exposes it for autowiring, and adds the two pieces
 * an application needs around it: console commands and a webhook endpoint.
 */
final class FopostBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        /** @var ArrayNodeDefinition $root */
        $root = $definition->rootNode();

        $root
            ->children()
                ->scalarNode('api_key')
                    ->isRequired()
                    ->cannotBeEmpty()
                    ->info('Your FoPost API key. Use %env(FOPOST_API_KEY)% rather than a literal.')
                ->end()
                ->scalarNode('base_url')
                    ->defaultValue(Client::DEFAULT_BASE_URL)
                    ->cannotBeEmpty()
                    ->info('API root. A host with no path gets the versioned API path appended for you.')
                ->end()
                ->floatNode('timeout')
                    ->defaultValue(30.0)
                    ->info('Seconds to wait for one request.')
                ->end()
                ->integerNode('max_retries')
                    ->defaultValue(3)
                    ->info('Attempts a rate limited request gets. Minimum 1.')
                ->end()
                ->scalarNode('default_workspace_id')
                    ->defaultNull()
                    ->info('Workspace the console commands use when none is passed.')
                ->end()
                ->scalarNode('webhook_secret')
                    ->defaultNull()
                    ->info('Secret shown once when the webhook was created. Without it every delivery is rejected.')
                ->end()
            ->end();
    }

    /** @param array<string, mixed> $config */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        foreach (
            [
                'api_key',
                'base_url',
                'timeout',
                'max_retries',
                'default_workspace_id',
                'webhook_secret',
            ] as $key
        ) {
            $builder->setParameter("fopost.{$key}", $config[$key] ?? null);
        }

        $services = $container->services()
            ->defaults()
                ->autowire()
                ->autoconfigure()
                ->private();

        $services->set('fopost.client', Client::class)
            ->autowire(false)
            ->arg('$apiKey', param('fopost.api_key'))
            ->arg('$baseUrl', param('fopost.base_url'))
            ->arg('$timeout', param('fopost.timeout'))
            ->arg('$maxRetries', param('fopost.max_retries'));

        // So a controller, a command, or any service can type hint the client.
        $services->alias(Client::class, 'fopost.client');

        $services->set(SignatureVerifier::class)
            ->arg('$secret', param('fopost.webhook_secret'));

        $services->set(WebhookController::class)
            ->tag('controller.service_arguments');

        $services->set(PostCommand::class)
            ->arg('$defaultWorkspaceId', param('fopost.default_workspace_id'));

        $services->set(AccountsCommand::class)
            ->arg('$defaultWorkspaceId', param('fopost.default_workspace_id'));
    }
}
