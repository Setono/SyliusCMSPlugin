<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Block\Heading;
use Setono\SyliusCMSPlugin\Tests\Application\Kernel;
use Setono\SyliusCMSPlugin\Tests\Functional\Fixture\DuplicateHeading;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;

final class BlockTypeRegistrationTest extends TestCase
{
    private string $cacheDir;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/setono_sylius_cms_' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->cacheDir);
    }

    /**
     * @test
     */
    public function two_block_types_with_the_same_code_fail_the_container_build(): void
    {
        $kernel = new class('test', true, $this->cacheDir) extends Kernel {
            public function __construct(string $environment, bool $debug, private readonly string $testCacheDir)
            {
                parent::__construct($environment, $debug);
            }

            public function getProjectDir(): string
            {
                return dirname(__DIR__) . '/Application';
            }

            public function getCacheDir(): string
            {
                return $this->testCacheDir;
            }

            protected function build(ContainerBuilder $container): void
            {
                parent::build($container);

                // Registered the way an app's services.yaml registers its classes: autoconfigured, with no tag of its own
                $container->register(DuplicateHeading::class, DuplicateHeading::class)->setAutoconfigured(true);
            }
        };

        try {
            $kernel->boot();
            self::fail('The container build must fail');
        } catch (\LogicException $e) {
            self::assertStringContainsString('The block type code "heading" is used by both', $e->getMessage());
            self::assertStringContainsString(Heading::class, $e->getMessage());
            self::assertStringContainsString(DuplicateHeading::class, $e->getMessage());
        }
    }
}
