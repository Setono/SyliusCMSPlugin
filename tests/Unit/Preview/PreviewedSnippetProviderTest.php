<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Preview;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\EntryFormat\EntryDenormalizer;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\Placement;
use Setono\SyliusCMSPlugin\Model\Snippet;
use Setono\SyliusCMSPlugin\Preview\Preview;
use Setono\SyliusCMSPlugin\Preview\PreviewContext;
use Setono\SyliusCMSPlugin\Preview\PreviewedEntryProvider;
use Setono\SyliusCMSPlugin\Preview\PreviewedSnippetProvider;
use Setono\SyliusCMSPlugin\Preview\PreviewTokenSigner;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyProviderInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\PlacementRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\RevisionRepositoryInterface;
use Setono\SyliusCMSPlugin\Revision\RevisionReader;
use Setono\SyliusCMSPlugin\Upgrade\StoredDataUpgraderInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The snippet a preview token shows, which the snippet preview's endpoints and plain page answer for. See "Snippet
 * preview" in docs/spec/builder.md
 */
final class PreviewedSnippetProviderTest extends TestCase
{
    use ProphecyTrait;

    private RequestStack $requestStack;

    /** @var ObjectProphecy<PlacementRepositoryInterface> */
    private ObjectProphecy $placements;

    protected function setUp(): void
    {
        $this->requestStack = new RequestStack();
        $this->placements = $this->prophesize(PlacementRepositoryInterface::class);
        $this->placements->findOneOfSnippet(Argument::cetera())->willReturn(null);
    }

    /**
     * @test
     */
    public function it_gives_the_previewed_snippet_s_working_copy_with_the_placement_its_token_names(): void
    {
        $snippet = new Snippet();
        $draft = new Snippet();
        $placement = new Placement($snippet, 'product.after_price', 0);
        $this->placements->findOneOfSnippet($draft, $placement->getUuid())->willReturn($placement);
        $this->previewing(new Preview($snippet->getUuid(), 'WEB', 'en_US', placementUuid: $placement->getUuid()));

        $previewed = $this->provider($snippet, $draft)->get();

        self::assertNotNull($previewed);
        self::assertSame($draft, $previewed->snippet);
        self::assertSame($placement, $previewed->placement);
        self::assertSame('en_US', $previewed->preview->localeCode);
    }

    /**
     * A snippet previewed on a plain page, or a placement removed since the token was signed
     *
     * @test
     */
    public function it_gives_no_placement_when_the_token_names_none_or_one_the_snippet_does_not_have(): void
    {
        $snippet = new Snippet();
        $this->previewing(new Preview($snippet->getUuid(), 'WEB', 'en_US'));
        self::assertNull($this->provider($snippet, $snippet)->get()?->placement);

        $this->previewing(new Preview($snippet->getUuid(), 'WEB', 'en_US', placementUuid: '0193a5be-0000-7000-8000-0000000000c1'));
        $previewed = $this->provider($snippet, $snippet)->get();
        self::assertNotNull($previewed);
        self::assertNull($previewed->placement);
    }

    /**
     * @test
     */
    public function it_gives_none_without_a_preview_of_a_snippet_in_the_locale(): void
    {
        $page = new Page();
        $snippet = new Snippet();

        $this->requestStack->push(new Request());
        self::assertNull($this->provider($snippet, $snippet)->get(), 'No preview');

        $this->previewing(new Preview($page->getUuid(), 'WEB', 'en_US'));
        self::assertNull($this->provider($page, $page)->get(), 'A page\'s preview');

        $this->previewing(new Preview($snippet->getUuid(), 'WEB', 'en_US'));
        self::assertNull($this->provider($snippet, $snippet)->get('da_DK'), 'Another locale');
        self::assertNotNull($this->provider($snippet, $snippet)->get('en_US'));
    }

    /**
     * The main request previews it
     */
    private function previewing(Preview $preview): void
    {
        $this->requestStack = new RequestStack();
        $this->requestStack->push(new Request([PreviewContext::QUERY_PARAMETER => (new PreviewTokenSigner('secret', new MockClock()))->sign($preview)]));
    }

    private function provider(EntryInterface $entry, EntryInterface $workingCopy): PreviewedSnippetProvider
    {
        $entries = $this->prophesize(EntryRepositoryInterface::class);
        $entries->findOneByUuid($entry->getUuid())->willReturn($entry);
        $workingCopies = $this->prophesize(WorkingCopyProviderInterface::class);
        $workingCopies->getWorkingCopy($entry)->willReturn($workingCopy);

        $previewContext = new PreviewContext($this->requestStack, new PreviewTokenSigner('secret', new MockClock()));

        return new PreviewedSnippetProvider($previewContext, new PreviewedEntryProvider(
            $previewContext,
            $entries->reveal(),
            $workingCopies->reveal(),
            $this->prophesize(ContentTypeRegistryInterface::class)->reveal(),
            $this->prophesize(RevisionRepositoryInterface::class)->reveal(),
            new RevisionReader($this->prophesize(StoredDataUpgraderInterface::class)->reveal(), new EntryDenormalizer(new ContentTypeRegistry([]))),
        ), $this->placements->reveal());
    }
}
