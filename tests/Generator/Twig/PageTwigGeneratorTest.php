<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Generator\Twig;

use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Generator\Twig\PageTwigGenerator;
use Setono\SyliusCMSPlugin\Generator\Twig\TwigGeneratorInterface;
use Setono\SyliusCMSPlugin\Model\ElementInterface;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Twig\Environment;

final class PageTwigGeneratorTest extends AbstractTwigGeneratorTest
{
    use ProphecyTrait;

    protected function getContext(): array
    {
        return ['localeCode' => 'en_US'];
    }

    protected function getElement(): ElementInterface
    {
        $block = $this->prophesize(PageInterface::class);
        $block->setCurrentLocale('en_US')->shouldBeCalled();
        $block->getContent()->willReturn('{"time":1709547935581,"blocks":[{"id":"cs1_2","type":"paragraph","data":{"text":"Content"}}],"version":"2.23.2"}');
        $block->getId()->willReturn(1);
        $block->getCode()->willReturn('code');
        $block->getType()->willReturn('page');

        return $block->reveal();
    }

    protected function getGenerator(Environment $twig): TwigGeneratorInterface
    {
        return new PageTwigGenerator($twig, 'page');
    }

    protected function getExpectedTwig(): string
    {
        return <<<TWIG
<div class="sscms-page sscms-page-code"><p>Content</p>
</div>

TWIG;
    }
}
