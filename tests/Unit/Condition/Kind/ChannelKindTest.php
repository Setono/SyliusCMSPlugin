<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Condition\Kind;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Attribute\NoParams;
use Setono\SyliusCMSPlugin\Condition\Kind\ChannelKind;
use Setono\SyliusCMSPlugin\Condition\Kind\ChannelParams;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Sylius\Component\Core\Model\ChannelInterface;

final class ChannelKindTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_matches_when_the_shops_channel_is_one_of_the_channels(): void
    {
        $params = new ChannelParams();
        $params->channels = ['WEB', 'B2B'];

        self::assertTrue((new ChannelKind())->matches($params, $this->context('WEB')));
        self::assertTrue((new ChannelKind())->matches($params, $this->context('B2B')));
        self::assertFalse((new ChannelKind())->matches($params, $this->context('OUTLET')));
    }

    /**
     * @test
     */
    public function it_matches_no_channel_without_channels(): void
    {
        self::assertFalse((new ChannelKind())->matches(new ChannelParams(), $this->context('WEB')));
    }

    /**
     * @test
     */
    public function it_takes_only_its_own_parameters(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('The channel condition takes %s, got %s', ChannelParams::class, NoParams::class));

        (new ChannelKind())->matches(new NoParams(), $this->context('WEB'));
    }

    private function context(string $channelCode): RenderContext
    {
        $channel = $this->prophesize(ChannelInterface::class);
        $channel->getCode()->willReturn($channelCode);

        return new RenderContext($channel->reveal(), 'en_US', 'USD', null, '@guest', new \DateTimeImmutable());
    }
}
