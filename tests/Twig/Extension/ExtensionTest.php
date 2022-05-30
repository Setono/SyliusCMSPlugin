<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusCMSPlugin\Twig\Extension;

use Setono\SyliusCMSPlugin\Generator\Page\PreviewLinkGeneratorInterface;
use Setono\SyliusCMSPlugin\Model\AssetInterface;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Previewer\Preview;
use Setono\SyliusCMSPlugin\Previewer\PreviewerInterface;
use Setono\SyliusCMSPlugin\Stack\ElementStack;
use Setono\SyliusCMSPlugin\Twig\Extension\Extension;
use Setono\SyliusCMSPlugin\Twig\Extension\Runtime;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Model\Channel;
use Sylius\Component\Channel\Model\ChannelInterface;
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
                $urlGenerator = new class() implements UrlGeneratorInterface {
                    public function setContext(RequestContext $context): void
                    {
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

                $channelContext = new class() implements ChannelContextInterface {
                    public function getChannel(): ChannelInterface
                    {
                        $channel = new Channel();
                        $channel->setCode('FASHION_WEB');

                        return $channel;
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

                return new Runtime(
                    $urlGenerator,
                    $previewLinkGenerator,
                    $previewer,
                    $channelContext,
                    $localeContext,
                    new ElementStack()
                );
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
