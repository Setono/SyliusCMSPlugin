<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Preview;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Setono\SyliusCMSPlugin\EntryFormat\EntryDenormalizer;
use Setono\SyliusCMSPlugin\EntryFormat\EntryNormalizer;
use Setono\SyliusCMSPlugin\Json\CanonicalJson;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Model\Revision;
use Setono\SyliusCMSPlugin\Preview\Preview;
use Setono\SyliusCMSPlugin\Preview\PreviewContext;
use Setono\SyliusCMSPlugin\Preview\PreviewedEntryProvider;
use Setono\SyliusCMSPlugin\Preview\PreviewTokenSigner;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyProviderInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeMetadata;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Rendering\EntryUrlMatch;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\RevisionRepositoryInterface;
use Setono\SyliusCMSPlugin\Revision\RevisionReader;
use Setono\SyliusCMSPlugin\Upgrade\StoredDataUpgraderInterface;
use Setono\SyliusCMSPlugin\Upgrade\Upgraded;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class PreviewedEntryProviderTest extends TestCase
{
    use ProphecyTrait;

    private RequestStack $requestStack;

    /** @var ObjectProphecy<EntryRepositoryInterface> */
    private ObjectProphecy $entryRepository;

    /** @var ObjectProphecy<WorkingCopyProviderInterface> */
    private ObjectProphecy $workingCopyProvider;

    /** @var ObjectProphecy<RevisionRepositoryInterface> */
    private ObjectProphecy $revisionRepository;

    private Page $entry;

    private Page $workingCopy;

    protected function setUp(): void
    {
        $this->requestStack = new RequestStack();
        $this->entry = new Page();

        // The working copy has a slug the published entry doesn't, as a draft can
        $this->workingCopy = new Page();
        $this->workingCopy->setCurrentLocale('en_US');
        $this->workingCopy->setSlug('about-us');

        $this->entryRepository = $this->prophesize(EntryRepositoryInterface::class);
        $this->entryRepository->findOneByUuid($this->entry->getUuid())->willReturn($this->entry);
        $this->workingCopyProvider = $this->prophesize(WorkingCopyProviderInterface::class);
        $this->workingCopyProvider->getWorkingCopy($this->entry)->willReturn($this->workingCopy);
        $this->revisionRepository = $this->prophesize(RevisionRepositoryInterface::class);
    }

    /**
     * @test
     */
    public function it_gives_the_working_copy_of_the_entry_the_request_previews(): void
    {
        $this->previewing($this->entry->getUuid(), 'en_US');

        self::assertSame($this->workingCopy, $this->provider()->getWorkingCopy('en_US'));
    }

    /**
     * @test
     */
    public function it_gives_nothing_in_another_locale_than_the_previews(): void
    {
        $this->previewing($this->entry->getUuid(), 'en_US');

        self::assertNull($this->provider()->getWorkingCopy('da_DK'));
    }

    /**
     * @test
     */
    public function it_gives_nothing_to_a_shopper(): void
    {
        $this->requestStack->push(new Request());
        $this->entryRepository->findOneByUuid(Argument::any())->shouldNotBeCalled();

        self::assertNull($this->provider()->getWorkingCopy('en_US'));
        self::assertNull($this->provider()->match('en_US', 'about-us'));
    }

    /**
     * @test
     */
    public function it_gives_nothing_when_the_previewed_entry_is_gone(): void
    {
        $this->previewing('0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e', 'en_US');
        $this->entryRepository->findOneByUuid('0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e')->willReturn(null);

        self::assertNull($this->provider()->getWorkingCopy('en_US'));
        self::assertNull($this->provider()->match('en_US', 'about-us'));
    }

    /**
     * @test
     */
    public function it_matches_the_previewed_entry_at_its_working_copys_url(): void
    {
        $this->previewing($this->entry->getUuid(), 'en_US');

        self::assertEquals(new EntryUrlMatch($this->workingCopy->getUuid(), 'page'), $this->provider()->match('en_US', 'about-us'));
        self::assertEquals(new EntryUrlMatch($this->workingCopy->getUuid(), 'page'), $this->provider('pg')->match('en_US', 'pg/about-us'));
    }

    /**
     * @test
     */
    public function it_matches_nothing_at_another_url(): void
    {
        $this->previewing($this->entry->getUuid(), 'en_US');

        self::assertNull($this->provider()->match('en_US', 'contact'));
        self::assertNull($this->provider('pg')->match('en_US', 'about-us'));
        self::assertNull($this->provider()->match('da_DK', 'about-us'));
    }

    /**
     * @test
     */
    public function it_gives_the_content_of_the_revision_a_read_only_preview_shows_and_matches_it_at_the_revisions_url(): void
    {
        $this->entry->setCurrentLocale('en_US');
        $this->entry->setTitle('About our shop');
        $this->entry->setSlug('about-our-shop');
        $content = clone $this->entry;
        $content->setTitle('About us');
        $content->setSlug('about-us-then');
        $revision = new Revision();
        $revision->setEntry($this->entry);
        $revision->setContent(CanonicalJson::encode((new EntryNormalizer(self::contentTypes()))->normalize($content)));
        $this->revisionRepository->findOneById(42)->willReturn($revision);
        $this->workingCopyProvider->getWorkingCopy(Argument::any())->shouldNotBeCalled();
        $this->previewing($this->entry->getUuid(), 'en_US', 42);

        $previewed = $this->provider(contentTypes: self::contentTypes())->getWorkingCopy('en_US');

        self::assertNotNull($previewed);
        self::assertNotSame($this->entry, $previewed);
        self::assertSame($this->entry->getUuid(), $previewed->getUuid());
        self::assertSame('About us', $previewed->getTranslation('en_US')->getTitle());
        self::assertSame('About our shop', $this->entry->getTranslation('en_US')->getTitle());
        self::assertNull($this->provider()->getWorkingCopy('da_DK'));

        self::assertEquals(new EntryUrlMatch($this->entry->getUuid(), 'page'), $this->provider(contentTypes: self::contentTypes())->match('en_US', 'about-us-then'));
        self::assertNull($this->provider(contentTypes: self::contentTypes())->match('en_US', 'about-our-shop'));
    }

    /**
     * @test
     */
    public function it_gives_nothing_when_the_previewed_revision_is_gone(): void
    {
        $this->previewing($this->entry->getUuid(), 'en_US', 42);
        $this->revisionRepository->findOneById(42)->willReturn(null);

        self::assertNull($this->provider()->getWorkingCopy('en_US'));
        self::assertNull($this->provider()->match('en_US', 'about-us'));
    }

    private function previewing(string $entryUuid, string $localeCode, ?int $revisionId = null): void
    {
        $signer = new PreviewTokenSigner('secret', new MockClock());
        $this->requestStack->push(new Request([PreviewContext::QUERY_PARAMETER => $signer->sign(new Preview($entryUuid, 'WEB', $localeCode, $revisionId))]));
    }

    private static function contentTypes(): ContentTypeRegistry
    {
        return new ContentTypeRegistry(['page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page']]);
    }

    private function provider(string $prefix = '', ?ContentTypeRegistryInterface $contentTypes = null): PreviewedEntryProvider
    {
        if (null === $contentTypes) {
            $prophecy = $this->prophesize(ContentTypeRegistryInterface::class);
            $prophecy->getByEntry($this->workingCopy)->willReturn(new ContentTypeMetadata('page', 'Page', 1, $prefix, null, Page::class, PageTranslation::class, 'setono_sylius_cms.page'));
            $contentTypes = $prophecy->reveal();
        }

        $upgrader = $this->prophesize(StoredDataUpgraderInterface::class);
        $upgrader->upgradeEntry(Argument::type('array'))->will(static fn (array $arguments): Upgraded => new Upgraded((array) $arguments[0]));

        return new PreviewedEntryProvider(
            new PreviewContext($this->requestStack, new PreviewTokenSigner('secret', new MockClock())),
            $this->entryRepository->reveal(),
            $this->workingCopyProvider->reveal(),
            $contentTypes,
            $this->revisionRepository->reveal(),
            new RevisionReader($upgrader->reveal(), new EntryDenormalizer(self::contentTypes())),
        );
    }
}
