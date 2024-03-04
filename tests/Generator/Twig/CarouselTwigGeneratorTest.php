<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Generator\Twig;

use Doctrine\Common\Collections\ArrayCollection;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Generator\Twig\CarouselTwigGenerator;
use Setono\SyliusCMSPlugin\Generator\Twig\TwigGeneratorInterface;
use Setono\SyliusCMSPlugin\Model\Block;
use Setono\SyliusCMSPlugin\Model\CarouselInterface;
use Setono\SyliusCMSPlugin\Model\ElementInterface;
use Twig\Environment;

final class CarouselTwigGeneratorTest extends AbstractTwigGeneratorTest
{
    use ProphecyTrait;

    protected function getElement(): ElementInterface
    {
        $block1 = new Block();
        $block1->setcode('block1_code');

        $block2 = new Block();
        $block2->setcode('block2_code');

        $carousel = $this->prophesize(CarouselInterface::class);
        $carousel->getId()->willReturn(1);
        $carousel->getCode()->willReturn('code');
        $carousel->getType()->willReturn('carousel');
        $carousel->getConfiguration()->willReturn([
            'showControls' => true,
            'infinite' => true,
            'slidesToShow' => 1,
            'slidesToScroll' => 1,
            'autoplay' => true,
            'autoplaySpeed' => 2000,
        ]);
        $carousel->getBlocks()->willReturn(new ArrayCollection([$block1, $block2]));

        return $carousel->reveal();
    }

    protected function getGenerator(Environment $twig): TwigGeneratorInterface
    {
        return new CarouselTwigGenerator($twig, 'carousel');
    }

    protected function getExpectedTwig(): string
    {
        return <<<TWIG
<div class="sscms-carousel sscms-carousel-code">
    <div class="items">
                    <div class="item">
                {{ sscms_block("block1_code") }}
            </div>
                    <div class="item">
                {{ sscms_block("block2_code") }}
            </div>
            </div>

            <div class="navigation">
            <button class="left ui huge black icon button">
                <i class="left arrow icon"></i>
            </button>
            <button class="right ui huge black icon button">
                <i class="right arrow icon"></i>
            </button>
        </div>
        <script src="https://code.jquery.com/jquery-3.6.0.min.js" integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>
    <script>
        $(document).ready(() => {
            $('.sscms-carousel-code .items').slick({
                infinite: true,
                slidesToShow: 1,
                slidesToScroll: 1,
                prevArrow: $('.sscms-carousel-code button.left'),
                nextArrow: $('.sscms-carousel-code button.right'),
                appendArrows: false,
                autoplay: true,
                autoplaySpeed: 2000,
            });
        });
    </script>
</div>

TWIG;
    }
}
