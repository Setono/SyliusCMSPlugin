<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Homepage;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\EntryFormat\EntryDenormalizer;
use Setono\SyliusCMSPlugin\Homepage\HomepageProviderInterface;
use Setono\SyliusCMSPlugin\Homepage\HomepageResolver;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Preview\Preview;
use Setono\SyliusCMSPlugin\Preview\PreviewContext;
use Setono\SyliusCMSPlugin\Preview\PreviewedEntryProvider;
use Setono\SyliusCMSPlugin\Preview\PreviewTokenSigner;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyProviderInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\HomepageRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\RevisionRepositoryInterface;
use Setono\SyliusCMSPlugin\Revision\RevisionReader;
use Setono\SyliusCMSPlugin\Tests\Application\Entity\Announcement;
use Setono\SyliusCMSPlugin\Tests\Support\Homepages;
use Setono\SyliusCMSPlugin\Upgrade\StoredDataUpgraderInterface;
use Sylius\Component\Core\Model\Channel;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Resolving the page each channel shows at its root URL. See "Rendering the homepage" in docs/spec/urls-and-seo.md
 */
final class HomepageResolverTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<HomepageProviderInterface> */
    private ObjectProphecy $homepages;

    /** @var ObjectProphecy<EntryRepositoryInterface> */
    private ObjectProphecy $entryRepository;

    /** @var ObjectProphecy<WorkingCopyProviderInterface> */
    private ObjectProphecy $workingCopies;

    private RequestStack $requestStack;

    protected function setUp(): void
    {
        $this->homepages = $this->prophesize(HomepageProviderInterface::class);
        $this->homepages->getHomepage(Argument::any())->willReturn(null);
        $this->entryRepository = $this->prophesize(EntryRepositoryInterface::class);
        $this->workingCopies = $this->prophesize(WorkingCopyProviderInterface::class);
        $this->workingCopies->getWorkingCopy(Argument::any())->will(static fn (array $arguments): mixed => $arguments[0]);
        $this->requestStack = new RequestStack();
    }

    /**
     * @test
     */
    public function each_channel_shows_its_own_homepage(): void
    {
        $web = self::context('WEB');
        $b2b = self::context('B2B');
        $home = new Page();
        $wholesale = new Page();
        $this->homepages->getHomepage($web)->willReturn($home);
        $this->homepages->getHomepage($b2b)->willReturn($wholesale);

        self::assertSame($home, $this->resolver()->resolve($web));
        self::assertSame($wholesale, $this->resolver()->resolve($b2b));
    }

    /**
     * A channel without a homepage the shopper can see shows Sylius's own homepage
     *
     * @test
     */
    public function a_channel_without_a_homepage_the_shopper_can_see_resolves_to_none(): void
    {
        self::assertNull($this->resolver()->resolve(self::context('WEB')));
    }

    /**
     * The builder previews a homepage at its channel's root URL, with its working copy, whatever its own visibility
     *
     * @test
     */
    public function the_builders_preview_of_the_channels_homepage_shows_its_working_copy(): void
    {
        $page = $this->page('WEB');
        $workingCopy = clone $page;
        $this->workingCopies->getWorkingCopy($page)->willReturn($workingCopy);
        $this->previewing($page, 'WEB', 'en_US');

        self::assertSame($workingCopy, $this->resolver()->resolve(self::context('WEB')));
        $this->homepages->getHomepage(Argument::any())->shouldNotHaveBeenCalled();
    }

    /**
     * @test
     */
    public function the_preview_of_a_page_that_is_not_the_channels_homepage_shows_the_channels_homepage(): void
    {
        $context = self::context('WEB');
        $home = new Page();
        $this->homepages->getHomepage($context)->willReturn($home);
        $this->previewing($this->page('B2B'), 'WEB', 'en_US');

        self::assertSame($home, $this->resolver()->resolve($context));
    }

    /**
     * @test
     */
    public function the_preview_of_an_entry_other_than_a_page_shows_the_channels_homepage(): void
    {
        $context = self::context('WEB');
        $home = new Page();
        $this->homepages->getHomepage($context)->willReturn($home);
        $announcement = new Announcement();
        $this->entryRepository->findOneByUuid($announcement->getUuid())->willReturn($announcement);
        $this->requestStack->push(new Request([PreviewContext::QUERY_PARAMETER => self::signer()->sign(new Preview($announcement->getUuid(), 'WEB', 'en_US'))]));

        self::assertSame($home, $this->resolver()->resolve($context));
    }

    /**
     * The preview's locale is the context's in preview mode, so a page previewed in another locale isn't previewed here
     *
     * @test
     */
    public function a_preview_in_another_locale_shows_the_channels_homepage(): void
    {
        $context = self::context('WEB');
        $this->previewing($this->page('WEB'), 'WEB', 'da_DK');

        self::assertNull($this->resolver()->resolve($context));
    }

    private function previewing(Page $page, string $channelCode, string $localeCode): void
    {
        $this->entryRepository->findOneByUuid($page->getUuid())->willReturn($page);
        $this->requestStack->push(new Request([PreviewContext::QUERY_PARAMETER => self::signer()->sign(new Preview($page->getUuid(), $channelCode, $localeCode))]));
    }

    private function resolver(): HomepageResolver
    {
        $previewedEntries = new PreviewedEntryProvider(
            new PreviewContext($this->requestStack, self::signer()),
            $this->entryRepository->reveal(),
            $this->workingCopies->reveal(),
            $this->prophesize(ContentTypeRegistryInterface::class)->reveal(),
            $this->prophesize(RevisionRepositoryInterface::class)->reveal(),
            new RevisionReader($this->prophesize(StoredDataUpgraderInterface::class)->reveal(), new EntryDenormalizer(new ContentTypeRegistry([]))),
        );

        return new HomepageResolver($this->homepages->reveal(), $previewedEntries);
    }

    private function page(string $homepageOf): Page
    {
        $page = new Page();
        Homepages::modifier($this->prophesize(HomepageRepositoryInterface::class)->reveal())->setHomepageOf($page, [$homepageOf]);

        return $page;
    }

    private static function signer(): PreviewTokenSigner
    {
        return new PreviewTokenSigner('secret', new MockClock());
    }

    private static function context(string $channelCode): RenderContext
    {
        $channel = new Channel();
        $channel->setCode($channelCode);

        return new RenderContext($channel, 'en_US', 'USD', null, '@guest', new \DateTimeImmutable());
    }
}
