<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Support;

use DAMA\DoctrineTestBundle\DAMADoctrineTestBundle;
use Playwright\Symfony\PlaywrightSymfonyBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The test application in its dev environment, with the web profiler collecting every request and its toolbar on every
 * page, plus what a test needs to drive it: the test container and a mocked session, a database transaction that's
 * rolled back after the test (dama/doctrine-test-bundle), Playwright's static files and the kept-in-memory transport
 */
final class DevKernel extends FixtureKernel
{
    /**
     * Whatever environment a test asks for, as KernelTestCase passes the one it runs in
     */
    public function __construct()
    {
        parent::__construct('dev', true);
    }

    public function registerBundles(): iterable
    {
        $bundles = [];
        foreach (parent::registerBundles() as $bundle) {
            $bundles[$bundle::class] = $bundle;
        }

        $bundles[DAMADoctrineTestBundle::class] ??= new DAMADoctrineTestBundle();
        $bundles[PlaywrightSymfonyBundle::class] ??= new PlaywrightSymfonyBundle();

        return array_values($bundles);
    }

    protected function cacheSuffix(): string
    {
        return 'tests';
    }

    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->loadFromExtension('framework', [
            'test' => true,
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
        ]);
        $container->loadFromExtension('dama_doctrine_test', [
            'enable_static_connection' => true,
            'enable_static_meta_data_cache' => true,
            'enable_static_query_cache' => true,
        ]);
        $container->loadFromExtension('playwright', ['assets' => ['prefixes' => ['/build', '/bundles', '/media', '/cms']]]);
        $container->register(KeptInMemoryTransportFactory::class, KeptInMemoryTransportFactory::class)->addTag('messenger.transport_factory');
    }
}
