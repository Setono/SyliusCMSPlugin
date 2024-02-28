<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\DependencyInjection;

use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Setono\SyliusCMSPlugin\DependencyInjection\SetonoSyliusCMSExtension;

/**
 * See examples of tests and configuration options here: https://github.com/SymfonyTest/SymfonyDependencyInjectionTest
 */
final class SetonoSyliusCMSExtensionTest extends AbstractExtensionTestCase
{
    protected function getContainerExtensions(): array
    {
        return [
            new SetonoSyliusCMSExtension(),
        ];
    }

    /**
     * @test
     */
    public function after_loading_services_and_parameters_are_set(): void
    {
        $this->load();

        $this->assertContainerBuilderHasParameter('setono_sylius_cms.templates', []);
    }
}
