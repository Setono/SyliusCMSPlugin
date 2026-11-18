<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Tests\Application\Kernel;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Code preset codes are unique across every registered directory, and a duplicate fails the container build. See "Code
 * presets" in docs/spec/layouts-and-presets.md
 */
final class PresetRegistrationTest extends TestCase
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
    public function two_code_presets_with_the_same_code_in_two_directories_fail_the_container_build(): void
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

                // A second directory, as a bundle prepends its own, with a hero.json as the test application has
                $container->loadFromExtension('setono_sylius_cms', ['presets' => ['paths' => [__DIR__ . '/Fixture/presets/duplicate']]]);
            }
        };

        try {
            $kernel->boot();
            self::fail('The container build must fail');
        } catch (\LogicException $e) {
            self::assertStringContainsString('The code preset code "hero" is used by both', $e->getMessage());
            self::assertStringContainsString('tests/Application/config/cms/presets/hero.json', $e->getMessage());
            self::assertStringContainsString('tests/Functional/Fixture/presets/duplicate/hero.json', $e->getMessage());
        }
    }
}
