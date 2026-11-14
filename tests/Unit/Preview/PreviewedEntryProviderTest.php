<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Preview;

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
use Setono\SyliusCMSPlugin\Rendering\EntryUrlMatch;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
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

    private function previewing(string $entryUuid, string $localeCode): void
    {
        $signer = new PreviewTokenSigner('secret', new MockClock());
        $this->requestStack->push(new Request([PreviewContext::QUERY_PARAMETER => $signer->sign(new Preview($entryUuid, 'WEB', $localeCode))]));
    }

    private function provider(string $prefix = ''): PreviewedEntryProvider
    {
        $contentTypes = $this->prophesize(ContentTypeRegistryInterface::class);
        $contentTypes->getByEntry($this->workingCopy)->willReturn(new ContentTypeMetadata('page', 'Page', 1, $prefix, null, Page::class, PageTranslation::class, 'setono_sylius_cms.page'));

        return new PreviewedEntryProvider(
            new PreviewContext($this->requestStack, new PreviewTokenSigner('secret', new MockClock())),
            $this->entryRepository->reveal(),
            $this->workingCopyProvider->reveal(),
            $contentTypes->reveal(),
        );
    }
}
