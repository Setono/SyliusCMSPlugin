<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusCMSPlugin\Twig\Extension;

use Setono\SyliusCMSPlugin\Generator\Page\PreviewLinkGeneratorInterface;
use Setono\SyliusCMSPlugin\Model\AssetInterface;
use Setono\SyliusCMSPlugin\Model\BlockInterface;
use Setono\SyliusCMSPlugin\Model\CarouselInterface;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Model\ViewInterface;
use Setono\SyliusCMSPlugin\Previewer\Preview;
use Setono\SyliusCMSPlugin\Previewer\PreviewerInterface;
use Setono\SyliusCMSPlugin\Renderer\RendererInterface;
use Setono\SyliusCMSPlugin\Renderer\Response;
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
    public function getRuntimeLoaders(): array
    {
        $runtimeLoader = new class() implements RuntimeLoaderInterface {
            public function load($class): Runtime
            {
                /** @var RendererInterface<BlockInterface> $blockRenderer */
                $blockRenderer = new class() implements RendererInterface {
                    public function render($element): Response
                    {
                        return new Response('block');
                    }
                };

                /** @var RendererInterface<ViewInterface> $viewRenderer */
                $viewRenderer = new class() implements RendererInterface {
                    public function render($element): Response
                    {
                        return new Response('view');
                    }
                };

                /** @var RendererInterface<CarouselInterface> $carouselRenderer */
                $carouselRenderer = new class() implements RendererInterface {
                    public function render($element): Response
                    {
                        return new Response('carousel');
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

                $previewLinkGenerator = new class() implements PreviewLinkGeneratorInterface {
                    public function generateAll(PageInterface $page): iterable
                    {
                        return [];
                    }
                };

                $previewer = new class() implements PreviewerInterface {
                    public function preview(AssetInterface $asset): Preview
                    {
                        return Preview::createUnavailablePreview();
                    }

                    public function supports(AssetInterface $asset): bool
                    {
                        return true;
                    }
                };

                return new Runtime($blockRenderer, $viewRenderer, $carouselRenderer, $urlGenerator, $localeContext, $previewLinkGenerator, $previewer);
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
