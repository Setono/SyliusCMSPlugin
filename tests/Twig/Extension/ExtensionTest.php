<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Twig\Extension;

use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
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
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
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
        $urlGenerator = $this->prophesize(UrlGeneratorInterface::class);
        $urlGenerator->generate(Argument::type('string'))->willReturn('route');

        $channel = new Channel();
        $channel->setCode('FASHION_WEB');
        $channelContext = $this->prophesize(ChannelContextInterface::class);
        $channelContext->getChannel()->willReturn($channel);

        $localeContext = $this->prophesize(LocaleContextInterface::class);
        $localeContext->getLocaleCode()->willReturn('en_US');

        $runtimeLoader = new class($urlGenerator->reveal(), $channelContext->reveal(), $localeContext->reveal()) implements RuntimeLoaderInterface {
            public function __construct(
                private readonly UrlGeneratorInterface $urlGenerator,
                private readonly ChannelContextInterface $channelContext,
                private readonly LocaleContextInterface $localeContext,
            ) {
            }

            public function load(string $class): Runtime
            {
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
                    $this->urlGenerator,
                    $previewLinkGenerator,
                    $previewer,
                    $this->channelContext,
                    $this->localeContext,
                    new ElementStack(),
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
