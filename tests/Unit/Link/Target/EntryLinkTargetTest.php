<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Link\Target;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Builder\Search\SearchInterface;
use Setono\SyliusCMSPlugin\Builder\Search\SearchResult;
use Setono\SyliusCMSPlugin\Field\Reference;
use Setono\SyliusCMSPlugin\Link\ResolvedLink;
use Setono\SyliusCMSPlugin\Link\Target\EntryLinkTarget;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Registry\AttributeReader;
use Setono\SyliusCMSPlugin\Rendering\ReferenceTypeLoaderInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Seo\CanonicalUrlGeneratorInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Rendering;

final class EntryLinkTargetTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ReferenceTypeLoaderInterface> */
    private ObjectProphecy $entries;

    /** @var ObjectProphecy<CanonicalUrlGeneratorInterface> */
    private ObjectProphecy $urlGenerator;

    /** @var ObjectProphecy<SearchInterface> */
    private ObjectProphecy $search;

    private ChannelInterface $channel;

    protected function setUp(): void
    {
        $this->entries = $this->prophesize(ReferenceTypeLoaderInterface::class);
        $this->urlGenerator = $this->prophesize(CanonicalUrlGeneratorInterface::class);
        $this->search = $this->prophesize(SearchInterface::class);
        $this->channel = $this->prophesize(ChannelInterface::class)->reveal();
    }

    /**
     * @test
     */
    public function it_is_the_entry_target_whose_ref_is_shared(): void
    {
        $attribute = AttributeReader::linkTarget(EntryLinkTarget::class);

        self::assertSame('entry', $attribute?->code);
        self::assertFalse($attribute->translatableRef);
    }

    /**
     * @test
     */
    public function it_resolves_an_entry_the_shopper_may_see_to_its_url_in_the_channel_and_locale_named_by_its_title(): void
    {
        $page = self::page();
        $context = $this->context();
        $this->entries->load([$page->getUuid()], $context)->willReturn([$page->getUuid() => $page]);
        $this->urlGenerator->generate($page, $this->channel, 'en_US')->willReturn('https://localhost/en_US/about');

        self::assertEquals(
            new ResolvedLink('entry', $page->getUuid(), 'https://localhost/en_US/about', 'About us'),
            $this->target()->resolve($page->getUuid(), $context),
        );
    }

    /**
     * @test
     */
    public function it_looks_an_entry_up_by_its_uuid_in_lower_case_and_keeps_the_ref_as_it_is(): void
    {
        $page = self::page();
        $context = $this->context();
        $this->entries->load([$page->getUuid()], $context)->willReturn([$page->getUuid() => $page]);
        $this->urlGenerator->generate($page, $this->channel, 'en_US')->willReturn('https://localhost/en_US/about');

        self::assertSame(strtoupper($page->getUuid()), $this->target()->resolve(strtoupper($page->getUuid()), $context)?->ref);
    }

    /**
     * @test
     */
    public function it_resolves_an_entry_the_shopper_may_not_see_to_nothing(): void
    {
        $page = self::page();
        $context = $this->context();
        $this->entries->load([$page->getUuid()], $context)->willReturn([]);

        self::assertNull($this->target()->resolve($page->getUuid(), $context));
    }

    /**
     * @test
     */
    public function it_leads_nowhere_for_an_entry_without_a_url_of_its_own(): void
    {
        $page = self::page();
        $this->urlGenerator->generate($page, $this->channel, 'en_US')->willReturn(null);

        self::assertNull($this->target()->link($page, $page->getUuid(), $this->context()));
    }

    /**
     * @test
     */
    public function it_refers_to_the_entry_by_its_uuid_in_lower_case_as_a_link(): void
    {
        self::assertEquals(new Reference('entry', '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e', false), $this->target()->reference('0193A5BE-71B0-7A3C-9D4E-5F6A7B8C9D0E'));
        self::assertNull($this->target()->reference('about-us'));
    }

    /**
     * @test
     */
    public function it_resolves_a_ref_that_is_not_a_uuid_to_nothing(): void
    {
        self::assertNull($this->target()->resolve('about-us', $this->context()));
    }

    /**
     * @test
     */
    public function it_searches_entries(): void
    {
        $this->search->search('about', 'en_US')->willReturn([new SearchResult('0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e', 'About us')]);

        self::assertSame([['ref' => '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e', 'name' => 'About us', 'thumbnail' => null]], [...$this->target()->search('about', 'en_US')]);
    }

    private function target(): EntryLinkTarget
    {
        return new EntryLinkTarget($this->entries->reveal(), $this->urlGenerator->reveal(), $this->search->reveal());
    }

    private static function page(): Page
    {
        $page = new Page();
        $page->setCurrentLocale('en_US');
        $page->setFallbackLocale('en_US');
        $page->setTitle('About us');
        $page->setSlug('about');

        return $page;
    }

    private function context(): RenderContext
    {
        return Rendering::context(channel: $this->channel);
    }
}
