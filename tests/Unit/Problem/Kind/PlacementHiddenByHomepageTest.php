<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Problem\Kind;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Model\Homepage;
use Setono\SyliusCMSPlugin\Model\HomepageInterface;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\Placement;
use Setono\SyliusCMSPlugin\Model\Snippet;
use Setono\SyliusCMSPlugin\Problem\Fix;
use Setono\SyliusCMSPlugin\Problem\Kind\PlacementHiddenByHomepage;
use Setono\SyliusCMSPlugin\Problem\Problem;
use Setono\SyliusCMSPlugin\Problem\Severity;
use Setono\SyliusCMSPlugin\Registry\SlotRegistry;
use Setono\SyliusCMSPlugin\Repository\HomepageRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\PlacementRepositoryInterface;
use Setono\SyliusCMSPlugin\Tests\Support\ChannelsAndAudience;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * A placement in a slot on Sylius's own homepage, such as store_homepage, in a channel whose homepage is a CMS page,
 * which doesn't render Sylius's homepage. See "Rendering the homepage" in docs/spec/urls-and-seo.md and "Problems" in
 * docs/spec/admin.md
 */
final class PlacementHiddenByHomepageTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<PlacementRepositoryInterface> */
    private ObjectProphecy $placements;

    /** @var ObjectProphecy<HomepageRepositoryInterface> */
    private ObjectProphecy $homepages;

    /** @var ObjectProphecy<ChannelRepositoryInterface<ChannelInterface>> */
    private ObjectProphecy $channels;

    protected function setUp(): void
    {
        $this->placements = $this->prophesize(PlacementRepositoryInterface::class);
        $this->homepages = $this->prophesize(HomepageRepositoryInterface::class);
        $this->channels = $this->prophesize(ChannelRepositoryInterface::class);
        $this->channels->findBy(['code' => ['GONE', 'WEB']])->willReturn([self::channel('WEB', 'Web store')]);
    }

    /**
     * @test
     */
    public function it_warns_for_each_placement_on_sylius_s_homepage_naming_the_snippet_s_channels_whose_homepage_is_a_cms_page(): void
    {
        $snippet = self::snippetIn('WEB', 'GONE', 'B2B');
        $this->placements->findBySnippet($snippet)->willReturn([
            new Placement($snippet, 'shop.footer', 0),
            new Placement($snippet, 'store_homepage', 0),
            new Placement($snippet, 'app.gone', 0),
        ]);
        $this->homepages->findByChannelCodes(['B2B', 'GONE', 'WEB'])->willReturn([new Homepage(new Page(), 'GONE'), new Homepage(new Page(), 'WEB')]);

        self::assertEquals([
            new Problem(
                'placement_hidden_by_homepage',
                Severity::Warning,
                $snippet,
                new TranslatableMessage('setono_sylius_cms.problem.placement_hidden_by_homepage', ['%slot%' => new TranslatableMessage('setono_sylius_cms.slot.store_homepage'), '%channels%' => 'GONE, Web store']),
                Fix::inPlacements($snippet),
            ),
        ], [...$this->kind()->findIn($snippet)]);
    }

    /**
     * @test
     */
    public function it_finds_nothing_when_none_of_the_snippet_s_channels_has_a_cms_homepage(): void
    {
        $snippet = self::snippetIn('B2B');
        $this->placements->findBySnippet($snippet)->willReturn([new Placement($snippet, 'store_homepage', 0)]);
        $this->homepages->findByChannelCodes(['B2B'])->willReturn([]);

        self::assertSame([], [...$this->kind()->findIn($snippet)]);
    }

    /**
     * @test
     */
    public function it_finds_nothing_for_a_snippet_not_on_sylius_s_homepage_or_an_entry_that_is_not_a_snippet(): void
    {
        $snippet = self::snippetIn('WEB');
        $this->placements->findBySnippet($snippet)->willReturn([new Placement($snippet, 'shop.footer', 0)]);
        $this->homepages->findByChannelCodes(Argument::any())->shouldNotBeCalled();

        self::assertSame([], [...$this->kind()->findIn($snippet)]);
        self::assertSame([], [...$this->kind()->findIn(new Page())]);
    }

    /**
     * A page made a channel's homepage, or no longer one, hides or shows the placements on Sylius's homepage
     *
     * @test
     */
    public function it_has_every_snippet_placed_on_sylius_s_homepage_counted_again_when_a_homepage_is_written(): void
    {
        $first = new Snippet();
        $second = new Snippet();
        $this->placements->findBySlots(['store_homepage'])->willReturn([
            new Placement($first, 'store_homepage', 0),
            new Placement($second, 'store_homepage', 1),
            new Placement($first, 'store_homepage', 2),
        ]);

        self::assertSame([HomepageInterface::class], PlacementHiddenByHomepage::getReferencedClasses());
        self::assertSame([], PlacementHiddenByHomepage::getReferenceFields());
        self::assertSame([$first, $second], [...$this->kind()->findEntriesReferencing([new Homepage(new Page(), 'WEB')])]);
    }

    private function kind(): PlacementHiddenByHomepage
    {
        return new PlacementHiddenByHomepage(
            $this->placements->reveal(),
            new SlotRegistry([
                'shop.footer' => ['label' => 'setono_sylius_cms.slot.shop.footer', 'provides' => [], 'cap' => null, 'template_event' => ['name' => 'sylius.shop.layout.footer', 'priority' => -10]],
                'store_homepage' => ['label' => 'setono_sylius_cms.slot.store_homepage', 'provides' => [], 'cap' => null, 'template_event' => ['name' => 'sylius.shop.homepage', 'priority' => 100]],
            ]),
            $this->homepages->reveal(),
            $this->channels->reveal(),
        );
    }

    private static function snippetIn(string ...$channels): Snippet
    {
        $snippet = new Snippet();
        ChannelsAndAudience::modifier()->setChannels($snippet, $channels);

        return $snippet;
    }

    private static function channel(string $code, string $name): ChannelInterface
    {
        $channel = new Channel();
        $channel->setCode($code);
        $channel->setName($name);

        return $channel;
    }
}
