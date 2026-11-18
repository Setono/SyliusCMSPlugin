<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Rendering;

use Setono\SyliusCMSPlugin\Rendering\PlacementData;
use Setono\SyliusCMSPlugin\Rendering\PlacementProviderInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tests\Support\Placements;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Where the shop reads a channel's placements. See "Rendering services" in docs/spec/extending.md
 */
final class PlacementProviderTest extends KernelTestCase
{
    use Entries;
    use Placements;

    /**
     * @test
     */
    public function it_gives_the_placements_of_the_channel_s_snippets_published_or_not_in_slot_and_position_order_as_plain_values(): void
    {
        self::bootKernel();
        self::createChannel('OUTLET', 'Outlet');
        $published = self::createSnippet('Free shipping', published: true);
        $draft = self::createSnippet('Opening hours');
        $outletOnly = self::createSnippet('Outlet sale', published: true, channels: ['OUTLET']);
        $footer = self::place($published, 'shop.footer');
        $top = self::place($draft, 'shop.top');
        $secondInFooter = self::place($draft, 'shop.footer');
        self::place($outletOnly, 'shop.footer');

        self::assertEquals([
            new PlacementData($footer->getUuid(), $published->getUuid(), 'shop.footer', [], 0),
            new PlacementData($secondInFooter->getUuid(), $draft->getUuid(), 'shop.footer', [], 1),
            new PlacementData($top->getUuid(), $draft->getUuid(), 'shop.top', [], 0),
        ], self::provider()->getPlacements(self::channel('WEB')));
        // A new entry starts in every channel, so the outlet has those too
        self::assertSame(
            [$published->getUuid(), $draft->getUuid(), $outletOnly->getUuid(), $draft->getUuid()],
            array_map(static fn (PlacementData $placement): string => $placement->snippetUuid, self::provider()->getPlacements(self::channel('OUTLET'))),
        );
    }

    /**
     * A decorator can cache what it gives
     *
     * @test
     */
    public function it_gives_values_that_serialize(): void
    {
        self::bootKernel();
        $placement = self::place(self::createSnippet('Free shipping'), 'shop.footer');

        $placements = self::provider()->getPlacements(self::channel('WEB'));

        self::assertEquals($placements, unserialize(serialize($placements)));
        self::assertSame($placement->getUuid(), $placements[0]->uuid);
    }

    private static function provider(): PlacementProviderInterface
    {
        $provider = self::getContainer()->get(PlacementProviderInterface::class);
        self::assertInstanceOf(PlacementProviderInterface::class, $provider);

        return $provider;
    }

    private static function channel(string $code): ChannelInterface
    {
        /** @var ChannelRepositoryInterface<ChannelInterface> $channels */
        $channels = self::getContainer()->get('sylius.repository.channel');
        $channel = $channels->findOneByCode($code);
        self::assertInstanceOf(ChannelInterface::class, $channel);

        return $channel;
    }
}
