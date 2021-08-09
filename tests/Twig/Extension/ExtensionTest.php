<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusCMSPlugin\Twig\Extension;

use Setono\SyliusCMSPlugin\Renderer\BlockRendererInterface;
use Setono\SyliusCMSPlugin\Renderer\ViewRendererInterface;
use Setono\SyliusCMSPlugin\Twig\Extension\Extension;
use Setono\SyliusCMSPlugin\Twig\Extension\Runtime;
use Twig\RuntimeLoader\RuntimeLoaderInterface;
use Twig\Test\IntegrationTestCase;

/**
 * @covers \Setono\SyliusCMSPlugin\Twig\Extension\Extension
 * @covers \Setono\SyliusCMSPlugin\Twig\Extension\Runtime
 */
final class ExtensionTest extends IntegrationTestCase
{
    public function getRuntimeLoaders(): array
    {
        $runtimeLoader = new class() implements RuntimeLoaderInterface {
            public function load($class): Runtime
            {
                $blockRenderer = new class() implements BlockRendererInterface {
                    public function render($block): string
                    {
                        return $block;
                    }
                };

                $viewRenderer = new class() implements ViewRendererInterface {
                    public function render($view): string
                    {
                        return $view;
                    }
                };

                return new Runtime($blockRenderer, $viewRenderer);
            }
        };

        return [$runtimeLoader];
    }

    public function getExtensions(): array
    {
        return [
            new Extension(),
        ];
    }

    protected function getFixturesDir(): string
    {
        return __DIR__ . '/../Fixtures/';
    }
}
