<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Routing;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Preview\Preview;
use Setono\SyliusCMSPlugin\Preview\PreviewContext;
use Setono\SyliusCMSPlugin\Preview\PreviewedEntryProvider;
use Setono\SyliusCMSPlugin\Preview\PreviewTokenSigner;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyProviderInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeMetadata;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Rendering\EntryUrlLookupInterface;
use Setono\SyliusCMSPlugin\Rendering\EntryUrlMatch;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Routing\EntryUrlMatcher;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Context\ChannelNotFoundException;
use Sylius\Component\Channel\Model\ChannelInterface as BaseChannelInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class EntryUrlMatcherTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ChannelContextInterface> */
    private ObjectProphecy $channelContext;

    /** @var ObjectProphecy<EntryUrlLookupInterface> */
    private ObjectProphecy $lookup;

    /** @var ObjectProphecy<ChannelInterface> */
    private ObjectProphecy $channel;

    /** @var ObjectProphecy<EntryRepositoryInterface> */
    private ObjectProphecy $entryRepository;

    private RequestStack $requestStack;

    protected function setUp(): void
    {
        $this->channel = $this->prophesize(ChannelInterface::class);
        $this->channel->getCode()->willReturn('WEB');

        $this->channelContext = $this->prophesize(ChannelContextInterface::class);
        $this->channelContext->getChannel()->willReturn($this->channel->reveal());

        $this->lookup = $this->prophesize(EntryUrlLookupInterface::class);
        $this->entryRepository = $this->prophesize(EntryRepositoryInterface::class);
        $this->requestStack = new RequestStack();
    }

    /**
     * @test
     */
    public function it_matches_a_url_an_entry_claims_in_the_channel_and_locale(): void
    {
        $match = new EntryUrlMatch('0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e', 'page');
        $this->lookup->lookup($this->channel->reveal(), 'en_US', 'about-us')->willReturn($match);

        $matcher = $this->matcher();

        self::assertTrue($matcher->matches(['_locale' => 'en_US', 'path' => 'about-us']));
        self::assertSame($match, $matcher->match('en_US', 'about-us'));
    }

    /**
     * @test
     */
    public function it_does_not_match_a_url_no_entry_claims(): void
    {
        $this->lookup->lookup(Argument::cetera())->willReturn(null);

        self::assertFalse($this->matcher()->matches(['_locale' => 'en_US', 'path' => 'nothing']));
    }

    /**
     * @test
     */
    public function it_does_not_match_without_a_locale_or_a_path(): void
    {
        $this->lookup->lookup(Argument::cetera())->shouldNotBeCalled();

        self::assertFalse($this->matcher()->matches(['path' => 'about-us']));
        self::assertFalse($this->matcher()->matches(['_locale' => 'en_US']));
        self::assertFalse($this->matcher()->matches(['_locale' => ['en_US'], 'path' => 'about-us']));
    }

    /**
     * @test
     */
    public function it_does_not_match_without_a_channel(): void
    {
        $this->channelContext->getChannel()->willThrow(new ChannelNotFoundException());
        $this->lookup->lookup(Argument::cetera())->shouldNotBeCalled();

        self::assertNull($this->matcher()->match('en_US', 'about-us'));
    }

    /**
     * @test
     */
    public function it_does_not_match_with_a_channel_that_is_not_a_shop_channel(): void
    {
        $this->channelContext->getChannel()->willReturn($this->prophesize(BaseChannelInterface::class)->reveal());
        $this->lookup->lookup(Argument::cetera())->shouldNotBeCalled();

        self::assertNull($this->matcher()->match('en_US', 'about-us'));
    }

    /**
     * @test
     */
    public function it_looks_each_url_up_once_until_it_is_reset(): void
    {
        $this->lookup->lookup($this->channel->reveal(), 'en_US', 'about-us')->willReturn(null)->shouldBeCalledTimes(2);
        $this->lookup->lookup($this->channel->reveal(), 'da_DK', 'about-us')->willReturn(null)->shouldBeCalledOnce();
        $this->lookup->lookup($this->channel->reveal(), 'en_US', 'contact')->willReturn(null)->shouldBeCalledOnce();

        $matcher = $this->matcher();
        $matcher->match('en_US', 'about-us');
        $matcher->match('en_US', 'about-us');
        $matcher->match('da_DK', 'about-us');
        $matcher->match('en_US', 'contact');

        $matcher->reset();
        $matcher->match('en_US', 'about-us');
    }

    /**
     * @test
     */
    public function it_looks_a_url_up_again_in_another_channel(): void
    {
        $other = $this->prophesize(ChannelInterface::class);
        $other->getCode()->willReturn('B2B');

        $this->lookup->lookup($this->channel->reveal(), 'en_US', 'about-us')->willReturn(null)->shouldBeCalledOnce();
        $this->lookup->lookup($other->reveal(), 'en_US', 'about-us')->willReturn(null)->shouldBeCalledOnce();

        $matcher = $this->matcher();
        $matcher->match('en_US', 'about-us');

        $this->channelContext->getChannel()->willReturn($other->reveal());
        $matcher->match('en_US', 'about-us');
        $matcher->match('en_US', 'about-us');
    }

    /**
     * @test
     */
    public function in_the_builders_preview_it_matches_the_edited_entry_at_its_working_copys_url_first(): void
    {
        $page = new Page();
        $page->setCurrentLocale('en_US');
        $page->setSlug('about-us');
        $this->entryRepository->findOneByUuid($page->getUuid())->willReturn($page);
        $this->previewing(new Preview($page->getUuid(), 'WEB', 'en_US'));

        $this->lookup->lookup($this->channel->reveal(), 'en_US', 'about-us')->shouldNotBeCalled();
        $this->lookup->lookup($this->channel->reveal(), 'en_US', 'contact')->willReturn(null)->shouldBeCalledOnce();

        $matcher = $this->matcher();

        self::assertEquals(new EntryUrlMatch($page->getUuid(), 'page'), $matcher->match('en_US', 'about-us'));
        self::assertNull($matcher->match('en_US', 'contact'), 'Other URLs are looked up as for shoppers');
    }

    private function previewing(Preview $preview): void
    {
        $signer = new PreviewTokenSigner('secret', new MockClock());
        $this->requestStack->push(new Request([PreviewContext::QUERY_PARAMETER => $signer->sign($preview)]));
    }

    private function matcher(): EntryUrlMatcher
    {
        $contentTypes = $this->prophesize(ContentTypeRegistryInterface::class);
        $contentTypes->getByEntry(Argument::type(Page::class))->willReturn(new ContentTypeMetadata('page', 'Page', 1, '', null, Page::class, PageTranslation::class, 'setono_sylius_cms.page'));

        $workingCopies = $this->prophesize(WorkingCopyProviderInterface::class);
        $workingCopies->getWorkingCopy(Argument::type(Page::class))->willReturnArgument(0);

        $previewedEntries = new PreviewedEntryProvider(
            new PreviewContext($this->requestStack, new PreviewTokenSigner('secret', new MockClock())),
            $this->entryRepository->reveal(),
            $workingCopies->reveal(),
            $contentTypes->reveal(),
        );

        return new EntryUrlMatcher($this->channelContext->reveal(), $this->lookup->reveal(), $previewedEntries);
    }
}
