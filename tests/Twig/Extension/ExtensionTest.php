<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusCMSPlugin\Twig\Extension;

use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Renderer\BlockRendererInterface;
use Setono\SyliusCMSPlugin\Renderer\ViewRendererInterface;
use Setono\SyliusCMSPlugin\Twig\Extension\Extension;
use Setono\SyliusCMSPlugin\Twig\Extension\Runtime;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Twig\RuntimeLoader\RuntimeLoaderInterface;
use Twig\Test\IntegrationTestCase;

/**
 * @covers \Setono\SyliusCMSPlugin\Twig\Extension\Extension
 * @covers \Setono\SyliusCMSPlugin\Twig\Extension\Runtime
 */
final class ExtensionTest extends IntegrationTestCase
{
    use ProphecyTrait;

    public function getRuntimeLoaders(): array
    {
        $runtimeLoader = new class() implements RuntimeLoaderInterface {
            public function load($class): Runtime
            {
                $blockRenderer = new class() implements BlockRendererInterface {
                    public function render($block): string
                    {
                        return 'block';
                    }
                };

                $viewRenderer = new class() implements ViewRendererInterface {
                    public function render($view): string
                    {
                        return 'view';
                    }
                };

                $urlGenerator = new class() implements UrlGeneratorInterface {
                    public function setContext(RequestContext $context): void
                    {
                        // TODO: Implement setContext() method.
                    }

                    public function getContext()
                    {
                        return new RequestContext();
                    }

                    public function generate(
                        string $name,
                        array $parameters = [],
                        int $referenceType = self::ABSOLUTE_PATH
                    ): string {
                        return 'route';
                    }
                };

                $localeContext = new class() implements LocaleContextInterface {
                    public function getLocaleCode(): string
                    {
                        return 'en_US';
                    }
                };

                return new Runtime($blockRenderer, $viewRenderer, $urlGenerator, $localeContext);
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
