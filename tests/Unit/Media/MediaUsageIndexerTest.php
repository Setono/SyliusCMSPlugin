<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Media;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\EntryFormat\InvalidEntryFormatException;
use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Field\MediaHandler;
use Setono\SyliusCMSPlugin\Field\MediaListHandler;
use Setono\SyliusCMSPlugin\Media\MediaUsageIndexer;
use Setono\SyliusCMSPlugin\Model\Draft;
use Setono\SyliusCMSPlugin\Model\EditorPreset;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\MediaUsage;
use Setono\SyliusCMSPlugin\Model\MediaUsageInterface;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Preset\EditorPresetContent;
use Setono\SyliusCMSPlugin\Preset\Preset;
use Setono\SyliusCMSPlugin\Preset\StartingPoint;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyProviderInterface;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistry;
use Setono\SyliusCMSPlugin\Tests\Unit\Media\Fixture\Article;
use Setono\SyliusCMSPlugin\Tests\Unit\Media\Fixture\LinkedHandler;
use Setono\SyliusCMSPlugin\Tests\Unit\Tree\Fixture\Hero;
use Setono\SyliusCMSPlugin\Tree\InvalidStoredTreeException;
use Setono\SyliusCMSPlugin\Tree\ReferenceFinder;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredLayout;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Setono\SyliusCMSPlugin\Tree\TreeValues;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class MediaUsageIndexerTest extends TestCase
{
    use ProphecyTrait;

    private const BLOCK = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4a';

    private const A = '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0a';

    private const B = '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0b';

    private const C = '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0c';

    private const D = '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0d';

    /** @var ObjectProphecy<WorkingCopyProviderInterface> */
    private ObjectProphecy $workingCopies;

    protected function setUp(): void
    {
        $this->workingCopies = $this->prophesize(WorkingCopyProviderInterface::class);
    }

    /**
     * @test
     */
    public function it_indexes_the_media_items_a_never_published_entry_uses_as_in_its_draft(): void
    {
        $entry = self::page(false, self::article(cover: self::A, images: [self::B, self::A]));
        $this->workingCopyIs($entry, null, $entry);

        $changes = $this->indexer()->index($entry, null, []);

        self::assertSame([[self::A, true, false], [self::B, true, false]], self::rows($changes->created, $entry));
        self::assertSame([], $changes->updated);
        self::assertSame([], $changes->removed);
    }

    /**
     * @test
     */
    public function it_indexes_a_published_entrys_published_content_and_its_draft_apart(): void
    {
        $entry = self::page(true, self::article(cover: self::A, images: [self::C]));
        $draft = new Draft();
        $this->workingCopyIs($entry, null, $entry);
        $this->workingCopyIs($entry, $draft, self::page(true, self::article(cover: self::A, images: [self::B])));

        $changes = $this->indexer()->index($entry, $draft, []);

        self::assertSame([[self::A, true, true], [self::B, true, false], [self::C, false, true]], self::rows($changes->created, $entry));
    }

    /**
     * @test
     */
    public function it_indexes_what_a_published_entry_without_a_draft_uses_as_in_both(): void
    {
        $entry = self::page(true, self::article(cover: self::A));
        $this->workingCopyIs($entry, null, $entry);

        self::assertSame([[self::A, true, true]], self::rows($this->indexer()->index($entry, null, [])->created, $entry));
    }

    /**
     * @test
     */
    public function it_works_out_the_rows_to_create_change_and_remove_against_the_rows_the_index_has(): void
    {
        $entry = self::page(true, self::article(cover: self::A, images: [self::B]));
        $draft = new Draft();
        $this->workingCopyIs($entry, null, $entry);
        $this->workingCopyIs($entry, $draft, self::page(true, self::article(cover: self::A, images: [self::B, self::C])));

        $unchanged = self::row($entry, self::A, true, true);
        $publishedOnly = self::row($entry, self::B, false, true);
        $gone = self::row($entry, self::D, true, false);

        $changes = $this->indexer()->index($entry, $draft, [$unchanged, $publishedOnly, $gone]);

        self::assertSame([[self::C, true, false]], self::rows($changes->created, $entry));
        self::assertSame([$publishedOnly], $changes->updated);
        self::assertTrue($publishedOnly->isInDraft());
        self::assertTrue($publishedOnly->isInPublished());
        self::assertSame([$gone], $changes->removed);
        self::assertFalse($changes->isEmpty());
    }

    /**
     * @test
     */
    public function it_changes_nothing_when_the_rows_already_match(): void
    {
        $entry = self::page(false, self::article(cover: self::A));
        $this->workingCopyIs($entry, null, $entry);

        self::assertTrue($this->indexer()->index($entry, null, [self::row($entry, self::A, true, false)])->isEmpty());
    }

    /**
     * @test
     */
    public function it_keeps_only_the_references_to_media_items_of_fields_that_link_to_other_things_too_such_as_rich_text(): void
    {
        $entry = self::page(false, new StoredTree(null, [new StoredBlock(self::BLOCK, 'article', 1, fields: ['body' => [
            'en_US' => [['type' => 'entry', 'id' => self::C], ['type' => 'media_item', 'id' => strtoupper(self::A)]],
            'da_DK' => [['type' => 'media_item', 'id' => self::B], ['type' => 'product', 'id' => 'MUG']],
        ]])]));
        $this->workingCopyIs($entry, null, $entry);

        self::assertSame([[self::A, true, false], [self::B, true, false]], self::rows($this->indexer()->index($entry, null, [])->created, $entry));
    }

    /**
     * @test
     */
    public function it_indexes_the_media_items_in_the_layout_and_in_blocks_inside_blocks(): void
    {
        $child = new StoredBlock('0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4b', 'article', 1, fields: ['cover' => self::B]);
        $entry = self::page(false, new StoredTree(new StoredLayout('hero', 1, ['image' => self::A]), [new StoredBlock(self::BLOCK, 'article', 1, children: [$child])]));
        $this->workingCopyIs($entry, null, $entry);

        self::assertSame([[self::A, true, false], [self::B, true, false]], self::rows($this->indexer()->index($entry, null, [])->created, $entry));
    }

    /**
     * @test
     */
    public function it_leaves_the_rows_as_they_are_when_the_draft_cant_be_read(): void
    {
        $entry = self::page(true, self::article(cover: self::A));
        $draft = new Draft();
        $this->workingCopyIs($entry, null, $entry);
        $this->workingCopies->getWorkingCopyWithDraft(Argument::type(EntryInterface::class), $draft)->willThrow(new InvalidEntryFormatException('Broken'));

        self::assertTrue($this->indexer()->index($entry, $draft, [self::row($entry, self::D, true, true)])->isEmpty());
    }

    /**
     * @test
     */
    public function it_leaves_the_rows_as_they_are_when_the_tree_in_the_rows_cant_be_read(): void
    {
        $entry = self::page(true, self::article(cover: self::A));
        $this->workingCopies->getWorkingCopyWithDraft(Argument::type(EntryInterface::class), null)->willThrow(new InvalidStoredTreeException('Broken'));

        self::assertTrue($this->indexer()->index($entry, null, [self::row($entry, self::D, true, true)])->isEmpty());
    }

    /**
     * @test
     */
    public function it_reads_a_copy_of_the_entry_so_the_entry_itself_is_left_as_it_is(): void
    {
        $entry = self::page(false, self::article(cover: self::A));
        $this->workingCopies->getWorkingCopyWithDraft(Argument::that(static fn (EntryInterface $copy): bool => $copy !== $entry && $copy->getUuid() === $entry->getUuid()), null)->willReturn($entry)->shouldBeCalled();

        $this->indexer()->index($entry, null, []);
    }

    /**
     * @test
     */
    public function it_indexes_the_media_items_an_editor_preset_uses_in_its_blocks_as_in_neither_draft_nor_published_content(): void
    {
        $preset = self::editorPreset(new Preset('Promo', self::article(cover: self::A, images: [self::B, self::A])->blocks));

        $changes = $this->indexer()->indexPreset($preset, []);

        self::assertSame([[self::A, $preset, false, false], [self::B, $preset, false, false]], array_map(static fn (MediaUsageInterface $row): array => [$row->getMediaItemUuid(), $row->getPreset(), $row->isInDraft(), $row->isInPublished()], $changes->created));
        self::assertNull($changes->created[0]->getEntry());
    }

    /**
     * @test
     */
    public function it_indexes_the_media_items_of_a_starting_point_s_layout(): void
    {
        $preset = self::editorPreset(new Preset('Landing page', [], startingPoint: new StartingPoint('page', new StoredLayout('hero', 1, ['image' => self::C]))));

        self::assertSame([self::C], array_map(static fn (MediaUsageInterface $row): string => $row->getMediaItemUuid(), $this->indexer()->indexPreset($preset, [])->created));
    }

    /**
     * @test
     */
    public function it_keeps_the_rows_of_the_media_items_an_editor_preset_still_uses_and_removes_the_others(): void
    {
        $preset = self::editorPreset(new Preset('Promo', self::article(cover: self::A, images: [self::B])->blocks));
        $kept = self::presetRow($preset, self::A);
        $gone = self::presetRow($preset, self::D);

        $changes = $this->indexer()->indexPreset($preset, [$kept, $gone]);

        self::assertSame([self::B], array_map(static fn (MediaUsageInterface $row): string => $row->getMediaItemUuid(), $changes->created));
        self::assertSame([], $changes->updated);
        self::assertSame([$gone], $changes->removed);
    }

    /**
     * @test
     */
    public function it_leaves_the_rows_of_an_editor_preset_it_can_not_read_as_they_are(): void
    {
        $preset = new EditorPreset();
        $preset->setContent('{"format": 1}');

        self::assertTrue($this->indexer()->indexPreset($preset, [self::presetRow($preset, self::A)])->isEmpty());
    }

    private static function editorPreset(Preset $content): EditorPreset
    {
        $preset = new EditorPreset();
        (new EditorPresetContent())->write($preset, new Preset($content->name, $content->blocks, startingPoint: $content->startingPoint, changedAt: new \DateTimeImmutable('2026-11-10T08:00:00Z')));

        return $preset;
    }

    private static function presetRow(EditorPreset $preset, string $uuid): MediaUsageInterface
    {
        $row = new MediaUsage();
        $row->setPreset($preset);
        $row->setMediaItemUuid($uuid);

        return $row;
    }

    private function indexer(): MediaUsageIndexer
    {
        $factory = $this->prophesize(FactoryInterface::class);
        $factory->createNew()->will(static fn (): MediaUsage => new MediaUsage());

        /** @var FactoryInterface<MediaUsageInterface> $usageFactory */
        $usageFactory = $factory->reveal();

        return new MediaUsageIndexer(
            $this->workingCopies->reveal(),
            new ReferenceFinder(
                new TreeValues(
                    new BlockTypeRegistry(['article' => Article::class], new FieldReader()),
                    new LayoutRegistry(['page' => ['hero' => Hero::class]], new FieldReader()),
                ),
                new FieldHandlerLocator(new ServiceLocator([
                    MediaHandler::class => static fn (): MediaHandler => new MediaHandler(),
                    MediaListHandler::class => static fn (): MediaListHandler => new MediaListHandler(),
                    LinkedHandler::class => static fn (): LinkedHandler => new LinkedHandler(),
                ])),
            ),
            new ContentTypeRegistry(['page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page']]),
            $usageFactory,
        );
    }

    /**
     * The working copy of a copy of the entry, with this draft
     */
    private function workingCopyIs(EntryInterface $entry, ?Draft $draft, EntryInterface $workingCopy): void
    {
        $this->workingCopies->getWorkingCopyWithDraft(Argument::that(static fn (EntryInterface $copy): bool => $copy->getUuid() === $entry->getUuid()), $draft)->willReturn($workingCopy);
    }

    /**
     * @param list<string> $images
     */
    private static function article(?string $cover = null, array $images = []): StoredTree
    {
        return new StoredTree(null, [new StoredBlock(self::BLOCK, 'article', 1, fields: ['cover' => $cover, 'images' => $images])]);
    }

    private static function page(bool $published, StoredTree $tree): Page
    {
        $page = new Page();
        $page->setBlockTree($tree);
        if ($published) {
            $page->publish(new \DateTimeImmutable('2026-11-01 10:00:00'));
        }

        return $page;
    }

    private static function row(EntryInterface $entry, string $uuid, bool $inDraft, bool $inPublished): MediaUsageInterface
    {
        $row = new MediaUsage();
        $row->setEntry($entry);
        $row->setMediaItemUuid($uuid);
        $row->setInDraft($inDraft);
        $row->setInPublished($inPublished);

        return $row;
    }

    /**
     * Each row as [its media item's UUID, in the draft, in the published content], in the order of the UUIDs, checking
     * it's the entry's
     *
     * @param list<MediaUsageInterface> $rows
     *
     * @return list<array{string, bool, bool}>
     */
    private static function rows(array $rows, EntryInterface $entry): array
    {
        $found = [];
        foreach ($rows as $row) {
            self::assertSame($entry, $row->getEntry());
            $found[] = [$row->getMediaItemUuid(), $row->isInDraft(), $row->isInPublished()];
        }

        usort($found, static fn (array $a, array $b): int => $a[0] <=> $b[0]);

        return $found;
    }
}
