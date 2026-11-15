<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Factory;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Factory\EntryFactory;
use Setono\SyliusCMSPlugin\Model\Page;
use Sylius\Component\Channel\Model\Channel;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;

final class EntryFactoryTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_starts_a_new_entry_in_every_channel(): void
    {
        $page = new Page();
        $decorated = $this->prophesize(FactoryInterface::class);
        $decorated->createNew()->willReturn($page);

        $channels = $this->prophesize(ChannelRepositoryInterface::class);
        $channels->findAll()->willReturn([self::channel('WEB'), self::channel('B2B')]);

        $entry = (new EntryFactory($decorated->reveal(), $channels->reveal()))->createNew();

        self::assertSame($page, $entry);
        self::assertSame(['B2B', 'WEB'], $entry->getChannelCodes());
    }

    /**
     * @test
     */
    public function it_needs_the_factory_it_decorates_to_create_an_entry(): void
    {
        $decorated = $this->prophesize(FactoryInterface::class);
        $decorated->createNew()->willReturn(new \stdClass());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(\stdClass::class);

        (new EntryFactory($decorated->reveal(), $this->prophesize(ChannelRepositoryInterface::class)->reveal()))->createNew();
    }

    private static function channel(string $code): ChannelInterface
    {
        $channel = new Channel();
        $channel->setCode($code);

        return $channel;
    }
}
