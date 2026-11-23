<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Targeting;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Targeting\ChannelNames;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\Channel;

final class ChannelNamesTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_names_channels_by_their_names_or_by_their_codes_when_they_have_none_or_were_deleted_in_the_order_of_the_codes(): void
    {
        /** @var ObjectProphecy<ChannelRepositoryInterface<ChannelInterface>> $channels */
        $channels = $this->prophesize(ChannelRepositoryInterface::class);
        $channels->findBy(['code' => ['WEB', 'OUTLET', 'B2B']])->willReturn([self::channel('B2B', null), self::channel('WEB', 'Web store')]);

        self::assertSame(['WEB' => 'Web store', 'OUTLET' => 'OUTLET', 'B2B' => 'B2B'], ChannelNames::of($channels->reveal(), ['WEB', 'OUTLET', 'B2B']));
    }

    /**
     * @test
     */
    public function it_looks_nothing_up_for_no_codes(): void
    {
        /** @var ObjectProphecy<ChannelRepositoryInterface<ChannelInterface>> $channels */
        $channels = $this->prophesize(ChannelRepositoryInterface::class);
        $channels->findBy(Argument::cetera())->shouldNotBeCalled();

        self::assertSame([], ChannelNames::of($channels->reveal(), []));
    }

    private static function channel(string $code, ?string $name): Channel
    {
        $channel = new Channel();
        $channel->setCode($code);
        $channel->setName($name);

        return $channel;
    }
}
