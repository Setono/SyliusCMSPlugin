<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Twig\Extension;

use Setono\SyliusCMSPlugin\Generator\Page\PreviewLinkGeneratorInterface;
use Setono\SyliusCMSPlugin\Model\AssetInterface;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Previewer\Preview;
use Setono\SyliusCMSPlugin\Previewer\PreviewerInterface;
use Setono\SyliusCMSPlugin\Renderer\RendererInterface;
use function sprintf;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Component\Resource\ResourceActions;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Throwable;
use Twig\Extension\RuntimeExtensionInterface;

final class Runtime implements RuntimeExtensionInterface
{
    private RendererInterface $blockRenderer;

    private RendererInterface $viewRenderer;

    private RendererInterface $carouselRenderer;

    private UrlGeneratorInterface $router;

    private LocaleContextInterface $localeContext;

    private PreviewLinkGeneratorInterface $previewLinkGenerator;

    private PreviewerInterface $previewer;

    public function __construct(
        RendererInterface $blockRenderer,
        RendererInterface $viewRenderer,
        RendererInterface $carouselRenderer,
        UrlGeneratorInterface $router,
        LocaleContextInterface $localeContext,
        PreviewLinkGeneratorInterface $previewLinkGenerator,
        PreviewerInterface $previewer
    ) {
        $this->blockRenderer = $blockRenderer;
        $this->viewRenderer = $viewRenderer;
        $this->carouselRenderer = $carouselRenderer;
        $this->router = $router;
        $this->localeContext = $localeContext;
        $this->previewLinkGenerator = $previewLinkGenerator;
        $this->previewer = $previewer;
    }

    public function block(string $block): string
    {
        return (string) $this->blockRenderer->render($block);
    }

    public function view(string $view): string
    {
        return (string) $this->viewRenderer->render($view);
    }

    public function carousel(string $carousel): string
    {
        return (string) $this->carouselRenderer->render($carousel);
    }

    public function linkToRoute(
        string $name,
        string $displayedValue = null,
        array $parameters = [],
        int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH
    ): string {
        try {
            $uri = $this->router->generate($name, $parameters, $referenceType);
            if (null === $displayedValue) {
                $displayedValue = $uri;
            }

            return sprintf('<a href="%s">%s</a>', $uri, $displayedValue);
        } catch (Throwable $exception) {
            return sprintf(
                '<!-- Tried to generate a link for an non existing route: %s (%s) -->',
                $name,
                $displayedValue ?? 'No display value'
            );
        }
    }

    public function linkToResource(
        string $alias,
        string $displayedValue = null,
        array $parameters = [],
        int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH,
        string $type = ResourceActions::SHOW,
        ?string $section = 'shop'
    ): string {
        $sectionPrefix = $section ? $section . '_' : '';

        [$applicationName, $resourceName] = explode('.', $alias);
        $routeName = sprintf('%s_%s%s_%s', $applicationName, $sectionPrefix, $resourceName, $type);

        return $this->linkToRoute($routeName, $displayedValue, $parameters, $referenceType);
    }

    public function linkToProduct(
        string $slug,
        string $displayedValue = null,
        string $localeCode = null,
        int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH
    ): string {
        if (null === $localeCode) {
            $localeCode = $this->localeContext->getLocaleCode();
        }

        return $this->linkToResource(
            'sylius.product',
            $displayedValue,
            ['slug' => $slug, '_locale' => $localeCode],
            $referenceType
        );
    }

    public function linkToTaxon(
        string $slug,
        string $displayedValue = null,
        string $localeCode = null,
        int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH
    ): string {
        if (null === $localeCode) {
            $localeCode = $this->localeContext->getLocaleCode();
        }

        return $this->linkToResource(
            'sylius.product',
            $displayedValue,
            ['slug' => $slug, '_locale' => $localeCode],
            $referenceType,
            ResourceActions::INDEX
        );
    }

    public function linkToPage(
        string $slug,
        string $displayedValue = null,
        string $localeCode = null,
        int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH
    ): string {
        if (null === $localeCode) {
            $localeCode = $this->localeContext->getLocaleCode();
        }

        return $this->linkToResource(
            'setono_sylius_cms.page',
            $displayedValue,
            ['slug' => $slug, '_locale' => $localeCode],
            $referenceType
        );
    }

    public function getPagePreviewLinks(PageInterface $page): iterable
    {
        return $this->previewLinkGenerator->generateAll($page);
    }

    public function preview(AssetInterface $asset): Preview
    {
        return $this->previewer->preview($asset);
    }
}
