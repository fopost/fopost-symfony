<?php

declare(strict_types=1);

namespace Fopost\Symfony\Tests;

use Fopost\Symfony\Event\FopostPostPublishedEvent;
use Fopost\Symfony\Event\FopostWebhookEvent;
use Fopost\Symfony\FopostBundle;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/** The smallest app that can boot the bundle: FrameworkBundle plus FopostBundle. */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    private readonly string $fingerprint;

    /** @param array<string, mixed> $fopost */
    public function __construct(private readonly array $fopost = ['api_key' => 'fp_test'])
    {
        $this->fingerprint = substr(md5(serialize($fopost)), 0, 12);

        parent::__construct('test', true);
    }

    /** @return iterable<int, \Symfony\Component\HttpKernel\Bundle\BundleInterface> */
    public function registerBundles(): iterable
    {
        return [new FrameworkBundle(), new FopostBundle()];
    }

    // Kept out of the package so a boot cannot drop generated files in the repository.
    public function getProjectDir(): string
    {
        return sys_get_temp_dir() . '/fopost-symfony-tests/' . $this->fingerprint;
    }

    public function getCacheDir(): string
    {
        return $this->getProjectDir() . '/cache';
    }

    public function getLogDir(): string
    {
        return $this->getProjectDir() . '/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'test' => true,
            'secret' => 'fopost-symfony-tests',
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'router' => ['utf8' => true],
        ]);

        $container->extension('fopost', $this->fopost);

        $services = $container->services();

        // Keeps the suite output clean; the framework logger writes to stderr in debug.
        $services->set('logger', NullLogger::class);

        $services->set('fopost.tests.transport', FakeTransport::class)->public();

        $services->set('fopost.tests.listener.any', RecordingListener::class)
            ->public()
            ->tag('kernel.event_listener', ['event' => FopostWebhookEvent::class, 'method' => '__invoke']);

        $services->set('fopost.tests.listener.post_published', RecordingListener::class)
            ->public()
            ->tag('kernel.event_listener', ['event' => FopostPostPublishedEvent::class, 'method' => '__invoke']);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@FopostBundle/config/routes/webhooks.yaml');
    }

    protected function build(ContainerBuilder $container): void
    {
        // Nothing in the suite may reach the network, so the SDK gets a stub transport.
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                if ($container->hasDefinition('fopost.client')) {
                    $container->getDefinition('fopost.client')
                        ->setArgument('$transport', new Reference('fopost.tests.transport'));
                }
            }
        });
    }
}
