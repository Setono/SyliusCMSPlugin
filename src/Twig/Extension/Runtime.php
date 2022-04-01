<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Twig\Extension;

use Setono\SyliusCMSPlugin\Generator\Page\PreviewLinkGeneratorInterface;
use Setono\SyliusCMSPlugin\Model\AssetInterface;
use Setono\SyliusCMSPlugin\Model\BlockInterface;
use Setono\SyliusCMSPlugin\Model\CarouselInterface;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Model\ViewInterface;
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
    /** @var RendererInterface<BlockInterface> */
    private RendererInterface $blockRenderer;

    /** @var RendererInterface<ViewInterface> */
    private RendererInterface $viewRenderer;

    /** @var RendererInterface<CarouselInterface> */
    private RendererInterface $carouselRenderer;

    private UrlGeneratorInterface $router;

    private LocaleContextInterface $localeContext;

    private PreviewLinkGeneratorInterface $previewLinkGenerator;

    private PreviewerInterface $previewer;

    /**
     * @param RendererInterface<BlockInterface> $blockRenderer
     * @param RendererInterface<ViewInterface> $viewRenderer
     * @param RendererInterface<CarouselInterface> $carouselRenderer
     */
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

    /**
     * Returns the allowed upload size in bytes
     */
    public function maxUploadSize(): int
    {
        $convert = static function (string $size): int {
            if (is_numeric($size)) {
                return (int) $size;
            }

            if (preg_match('/^(\d+)([A-Z]+)?$/', $size, $matches) !== 1) {
                return 0;
            }

            // this means that the value is given in bytes directly
            if (!isset($matches[2])) {
                return (int) $matches[1];
            }

            [, $numeral, $unit] = $matches;

            $units = ['K' => 1024, 'M' => 1_048_576, 'G' => 1_073_741_824];

            if (!isset($units[$unit])) {
                return 0;
            }

            return (int) round($units[$unit] * (int) $numeral);
        };

        $postMaxSize = ini_get('post_max_size');
        switch ($postMaxSize) {
            case 0:
                $postMaxSize = \PHP_INT_MAX; // see https://www.php.net/manual/en/ini.core.php#ini.post-max-size

                break;
            case false:
                $postMaxSize = 0;

                break;
            default:
                $postMaxSize = $convert($postMaxSize);

                break;
        }

        $uploadMaxSize = ini_get('upload_max_filesize');
        $uploadMaxSize = $uploadMaxSize === false ? 0 : $convert($uploadMaxSize);

        // Read here why we also need the memory_limit: https://www.php.net/manual/en/ini.core.php#ini.post-max-size
        $memoryLimit = ini_get('memory_limit');
        switch ($memoryLimit) {
            case -1:
                $memoryLimit = \PHP_INT_MAX;

                break;
            case false:
                $memoryLimit = 0;

                break;
            default:
                $memoryLimit = $convert($memoryLimit);
        }

        return min($postMaxSize, $uploadMaxSize, $memoryLimit);
    }

    public function readableBytes(int $bytes): string
    {
        $i = (int) floor(log($bytes) / log(1024));

        $sizes = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];

        return sprintf('%.02F %s', $bytes / (1024 ** $i), $sizes[$i]);
    }
}
