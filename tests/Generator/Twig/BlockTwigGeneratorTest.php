<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Generator\Twig;

use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Generator\Twig\BlockTwigGenerator;
use Setono\SyliusCMSPlugin\Generator\Twig\TwigGeneratorInterface;
use Setono\SyliusCMSPlugin\Model\BlockInterface;
use Setono\SyliusCMSPlugin\Model\ElementInterface;
use Twig\Environment;

final class BlockTwigGeneratorTest extends AbstractTwigGeneratorTest
{
    use ProphecyTrait;

    protected function getContext(): array
    {
        return ['localeCode' => 'en_US'];
    }

    protected function getElement(): ElementInterface
    {
        $block = $this->prophesize(BlockInterface::class);
        $block->setCurrentLocale('en_US')->shouldBeCalled();
        $block->getContent()->willReturn('content');
        $block->getId()->willReturn(1);
        $block->getCode()->willReturn('code');
        $block->getType()->willReturn('block');

        return $block->reveal();
    }

    protected function getGenerator(Environment $twig): TwigGeneratorInterface
    {
        return new BlockTwigGenerator($twig, 'block');
    }

    protected function getExpectedTwig(): string
    {
        return <<<TWIG
<div class="sscms-block sscms-block-code">content</div>

TWIG;
    }
}
