<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Twig\Extension;

use Setono\SyliusCMSPlugin\Renderer\BlockRendererInterface;
use Setono\SyliusCMSPlugin\Renderer\ViewRendererInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Component\Resource\ResourceActions;
use Symfony\Component\Routing\Exception\InvalidParameterException;
use Symfony\Component\Routing\Exception\MissingMandatoryParametersException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\RuntimeExtensionInterface;
use function sprintf;

final class Runtime implements RuntimeExtensionInterface
{
    private BlockRendererInterface $blockRenderer;

    private ViewRendererInterface $viewRenderer;

    private UrlGeneratorInterface $router;

    private LocaleContextInterface $localeContext;

    public function __construct(
        BlockRendererInterface $blockRenderer,
        ViewRendererInterface $viewRenderer,
        UrlGeneratorInterface $router,
        LocaleContextInterface $localeContext
    ) {
        $this->blockRenderer = $blockRenderer;
        $this->viewRenderer = $viewRenderer;
        $this->router = $router;
        $this->localeContext = $localeContext;
    }

    public function block(string $block): string
    {
        return $this->blockRenderer->render($block);
    }

    public function view(string $view): string
    {
        return $this->viewRenderer->render($view);
    }

    /**
     * @throws RouteNotFoundException              If the named route doesn't exist
     * @throws MissingMandatoryParametersException When some parameters are missing that are mandatory for the route
     * @throws InvalidParameterException           When a parameter value for a placeholder is not correct because
     *                                             it does not match the requirement
     */
    public function linkToRoute(
        string $name,
        string $displayedValue = null,
        array $parameters = [],
        int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH
    ): string {
        $uri = $this->router->generate($name, $parameters, $referenceType);
        if (null === $displayedValue) {
            $displayedValue = $uri;
        }

        return sprintf('<a href="%s">%s</a>', $uri, $displayedValue);
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
}
