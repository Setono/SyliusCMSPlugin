<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Preview;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\EntryFormat\EntryDenormalizer;
use Setono\SyliusCMSPlugin\Model\Snippet;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Preview\Preview;
use Setono\SyliusCMSPlugin\Preview\PreviewContext;
use Setono\SyliusCMSPlugin\Preview\PreviewedEntryProvider;
use Setono\SyliusCMSPlugin\Preview\PreviewPlacementPicker;
use Setono\SyliusCMSPlugin\Preview\PreviewTokenSigner;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyProviderInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\SlotMetadata;
use Setono\SyliusCMSPlugin\Rendering\CandidatePlacement;
use Setono\SyliusCMSPlugin\Rendering\PlacementData;
use Setono\SyliusCMSPlugin\Rendering\PlacementOutcome;
use Setono\SyliusCMSPlugin\Rendering\PlacementPickerInterface;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\RevisionRepositoryInterface;
use Setono\SyliusCMSPlugin\Revision\RevisionReader;
use Setono\SyliusCMSPlugin\Tests\Support\Rendering;
use Setono\SyliusCMSPlugin\Upgrade\StoredDataUpgraderInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * In a snippet's preview, the previewed placement wins in its slot with the snippet's draft. See "Snippet preview" in
 * docs/spec/builder.md
 */
final class PreviewPlacementPickerTest extends TestCase
{
    use ProphecyTrait;

    private const PLACEMENT = '0193a5be-0000-7000-8000-0000000000c1';

    private RequestStack $requestStack;

    private SnippetInterface $snippet;

    private SnippetInterface $draft;

    protected function setUp(): void
    {
        $this->requestStack = new RequestStack();
        $this->snippet = new Snippet();
        $this->draft = new Snippet();
    }

    /**
     * @test
     */
    public function it_lets_the_previewed_placement_win_in_its_slot_with_the_snippet_s_draft(): void
    {
        $this->previewing(self::PLACEMENT);
        $placement = new PlacementData(self::PLACEMENT, $this->snippet->getUuid(), 'shop.top', [], 0);

        $candidates = $this->picker([new CandidatePlacement($placement, PlacementOutcome::SnippetNotVisible)])->pick(self::slot(), [$placement], Rendering::context(previewMode: 'builder'));

        self::assertEquals([new CandidatePlacement($placement, PlacementOutcome::Wins, $this->draft, previewed: true)], $candidates);
    }

    /**
     * @test
     */
    public function it_picks_as_for_shoppers_in_a_slot_without_the_previewed_placement(): void
    {
        $this->previewing(self::PLACEMENT);
        $other = new PlacementData('0193a5be-0000-7000-8000-0000000000c2', $this->snippet->getUuid(), 'shop.top', [], 0);
        $inner = [new CandidatePlacement($other, PlacementOutcome::SnippetNotVisible)];

        self::assertSame($inner, $this->picker($inner)->pick(self::slot(), [$other], Rendering::context(previewMode: 'builder')));
    }

    /**
     * @test
     */
    public function it_picks_as_for_shoppers_outside_a_snippet_s_preview(): void
    {
        $this->requestStack->push(new Request());
        $placement = new PlacementData(self::PLACEMENT, $this->snippet->getUuid(), 'shop.top', [], 0);
        $inner = [new CandidatePlacement($placement, PlacementOutcome::SnippetNotVisible)];

        self::assertSame($inner, $this->picker($inner)->pick(self::slot(), [$placement], Rendering::context()));
    }

    private function previewing(string $placementUuid): void
    {
        $signer = new PreviewTokenSigner('secret', new MockClock());
        $this->requestStack->push(new Request([PreviewContext::QUERY_PARAMETER => $signer->sign(new Preview($this->snippet->getUuid(), 'WEB', 'en_US', placementUuid: $placementUuid))]));
    }

    /**
     * @param list<CandidatePlacement> $candidates what the inner picker answers
     */
    private function picker(array $candidates): PreviewPlacementPicker
    {
        $inner = $this->prophesize(PlacementPickerInterface::class);
        $inner->pick(Argument::cetera())->willReturn($candidates);

        $entries = $this->prophesize(EntryRepositoryInterface::class);
        $entries->findOneByUuid($this->snippet->getUuid())->willReturn($this->snippet);
        $workingCopies = $this->prophesize(WorkingCopyProviderInterface::class);
        $workingCopies->getWorkingCopy($this->snippet)->willReturn($this->draft);

        $previewContext = new PreviewContext($this->requestStack, new PreviewTokenSigner('secret', new MockClock()));

        return new PreviewPlacementPicker($inner->reveal(), $previewContext, new PreviewedEntryProvider(
            $previewContext,
            $entries->reveal(),
            $workingCopies->reveal(),
            $this->prophesize(ContentTypeRegistryInterface::class)->reveal(),
            $this->prophesize(RevisionRepositoryInterface::class)->reveal(),
            new RevisionReader($this->prophesize(StoredDataUpgraderInterface::class)->reveal(), new EntryDenormalizer(new ContentTypeRegistry([]))),
        ));
    }

    private static function slot(): SlotMetadata
    {
        return new SlotMetadata('shop.top', 'setono_sylius_cms.slot.shop.top', [], 1);
    }
}
