<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Twig\Extension;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Setono\SyliusCMSPlugin\Generator\Page\PreviewLinkGeneratorInterface;
use Setono\SyliusCMSPlugin\Model\AssetInterface;
use Setono\SyliusCMSPlugin\Model\ElementInterface;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Previewer\Preview;
use Setono\SyliusCMSPlugin\Previewer\PreviewerInterface;
use Setono\SyliusCMSPlugin\Stack\ElementId;
use Setono\SyliusCMSPlugin\Stack\ElementStackInterface;
use Setono\SyliusCMSPlugin\Twig\LogicalTemplateName;
use function sprintf;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Component\Resource\ResourceActions;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Throwable;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Extension\RuntimeExtensionInterface;
use Webmozart\Assert\Assert;

final class Runtime implements RuntimeExtensionInterface, LoggerAwareInterface
{
    private LoggerInterface $logger;

    public function __construct(
        private readonly UrlGeneratorInterface $router,
        private readonly PreviewLinkGeneratorInterface $previewLinkGenerator,
        private readonly PreviewerInterface $previewer,
        private readonly ChannelContextInterface $channelContext,
        private readonly LocaleContextInterface $localeContext,
        private readonly ElementStackInterface $elementStack,
    ) {
        $this->logger = new NullLogger();
    }

    public function asset(Environment $env, array $context, ?string $block, array $variables = []): string
    {
        return $this->renderElement($env, $context, $block, ElementInterface::TYPE_ASSET, $variables);
    }

    public function block(Environment $env, array $context, ?string $block, array $variables = []): string
    {
        return $this->renderElement($env, $context, $block, ElementInterface::TYPE_BLOCK, $variables);
    }

    public function carousel(Environment $env, array $context, ?string $carousel, array $variables = []): string
    {
        return $this->renderElement($env, $context, $carousel, ElementInterface::TYPE_CAROUSEL, $variables);
    }

    public function page(Environment $env, array $context, ?string $page, array $variables = []): string
    {
        return $this->renderElement($env, $context, $page, ElementInterface::TYPE_PAGE, $variables);
    }

    public function linkToRoute(
        string $name,
        string $displayedValue = null,
        array $parameters = [],
        int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH,
    ): string {
        try {
            $uri = $this->router->generate($name, $parameters, $referenceType);
            if (null === $displayedValue) {
                $displayedValue = $uri;
            }

            return sprintf('<a href="%s">%s</a>', $uri, $displayedValue);
        } catch (Throwable) {
            return sprintf(
                '<!-- Tried to generate a link for an non existing route: %s (%s) -->',
                $name,
                $displayedValue ?? 'No display value',
            );
        }
    }

    public function linkToResource(
        string $alias,
        string $displayedValue = null,
        array $parameters = [],
        int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH,
        string $type = ResourceActions::SHOW,
        ?string $section = 'shop',
    ): string {
        $sectionPrefix = null === $section ? '' : ($section . '_');

        [$applicationName, $resourceName] = explode('.', $alias);
        $routeName = sprintf('%s_%s%s_%s', $applicationName, $sectionPrefix, $resourceName, $type);

        return $this->linkToRoute($routeName, $displayedValue, $parameters, $referenceType);
    }

    public function linkToProduct(
        string $slug,
        string $displayedValue = null,
        string $localeCode = null,
        int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH,
    ): string {
        if (null === $localeCode) {
            $localeCode = $this->localeContext->getLocaleCode();
        }

        return $this->linkToResource(
            'sylius.product',
            $displayedValue,
            ['slug' => $slug, '_locale' => $localeCode],
            $referenceType,
        );
    }

    public function linkToTaxon(
        string $slug,
        string $displayedValue = null,
        string $localeCode = null,
        int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH,
    ): string {
        if (null === $localeCode) {
            $localeCode = $this->localeContext->getLocaleCode();
        }

        return $this->linkToResource(
            'sylius.product',
            $displayedValue,
            ['slug' => $slug, '_locale' => $localeCode],
            $referenceType,
            ResourceActions::INDEX,
        );
    }

    public function linkToPage(
        string $slug,
        string $displayedValue = null,
        string $localeCode = null,
        int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH,
    ): string {
        if (null === $localeCode) {
            $localeCode = $this->localeContext->getLocaleCode();
        }

        return $this->linkToResource(
            'setono_sylius_cms.page',
            $displayedValue,
            ['slug' => $slug, '_locale' => $localeCode],
            $referenceType,
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

    /**
     * @internal
     */
    public function pushToElementStack(int $id, string $code, string $identifier, string $type): void
    {
        $this->elementStack->push(new ElementId($id, $code, $identifier, $type));
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    private function renderElement(
        Environment $env,
        array $context,
        ?string $code,
        string $type,
        array $variables = [],
    ): string {
        if (null === $code) {
            return '';
        }

        $channelCode = $this->channelContext->getChannel()->getCode();
        Assert::notNull($channelCode);

        $logicalTemplateName = new LogicalTemplateName(
            $type,
            $channelCode,
            $this->localeContext->getLocaleCode(),
            $code,
        );

        try {
            /**
             * @psalm-suppress InternalMethod
             *
             * @var mixed $res
             */
            $res = $env->resolveTemplate((string) $logicalTemplateName)->render(array_merge($context, $variables));

            return is_string($res) ? $res : '';
        } catch (LoaderError $e) {
            $this->logger->error(sprintf(
                'An error occurred loading the %s "%s" (logical name: %s): %s',
                $type,
                $code,
                (string) $logicalTemplateName,
                $e->getMessage(),
            ));
        } catch (Throwable $e) {
            $this->logger->error(sprintf(
                'An error occurred trying to render %s "%s" (logical name: %s): %s',
                $type,
                $code,
                (string) $logicalTemplateName,
                $e->getMessage(),
            ));
        }

        return '';
    }
}
