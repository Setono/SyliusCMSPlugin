<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Generator\Twig;

use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Generator\Twig\AssetTwigGenerator;
use Setono\SyliusCMSPlugin\Generator\Twig\TwigGeneratorInterface;
use Setono\SyliusCMSPlugin\Model\AssetInterface;
use Setono\SyliusCMSPlugin\Model\ElementInterface;
use Twig\Environment;

final class AssetTwigGeneratorTest extends AbstractTwigGeneratorTest
{
    use ProphecyTrait;

    protected function getElement(): ElementInterface
    {
        $asset = $this->prophesize(AssetInterface::class);
        $asset->getMimeType()->willReturn('image/png');
        $asset->getId()->willReturn(1);
        $asset->getIdentifier()->willReturn('identifier');
        $asset->getCode()->willReturn('code');
        $asset->getType()->willReturn('asset');
        $asset->getName()->willReturn('name');

        return $asset->reveal();
    }

    protected function getGenerator(Environment $twig): TwigGeneratorInterface
    {
        return new AssetTwigGenerator($twig, 'asset');
    }

    protected function getExpectedTwig(): string
    {
        return <<<TWIG
<img src="/cms/asset/1" alt="name" class="sscms-asset identifier">
{% do sscms_push_to_element_stack(1, "code", "identifier", "asset") %}
TWIG;
    }
}
