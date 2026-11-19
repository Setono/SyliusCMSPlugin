<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Transfer;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Field\EntryHandler;
use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Field\MediaHandler;
use Setono\SyliusCMSPlugin\Field\SnippetHandler;
use Setono\SyliusCMSPlugin\Field\TextHandler;
use Setono\SyliusCMSPlugin\Field\ToggleHandler;
use Setono\SyliusCMSPlugin\Media\MediaKind;
use Setono\SyliusCMSPlugin\Model\EditorPreset;
use Setono\SyliusCMSPlugin\Model\EditorPresetInterface;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\EntryTranslation;
use Setono\SyliusCMSPlugin\Model\MediaFolder;
use Setono\SyliusCMSPlugin\Model\MediaFolderInterface;
use Setono\SyliusCMSPlugin\Model\MediaItem;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Model\Snippet;
use Setono\SyliusCMSPlugin\Preset\EditorPresetContent;
use Setono\SyliusCMSPlugin\Preset\Preset;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistry;
use Setono\SyliusCMSPlugin\Repository\EditorPresetRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\MediaFolderRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\MediaItemRepositoryInterface;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Shop;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\ShopTranslation;
use Setono\SyliusCMSPlugin\Tests\Unit\Transfer\Fixture\Teaser;
use Setono\SyliusCMSPlugin\Transfer\ExportPlan;
use Setono\SyliusCMSPlugin\Transfer\ExportPlanner;
use Setono\SyliusCMSPlugin\Transfer\ExportSelection;
use Setono\SyliusCMSPlugin\Transfer\InvalidExportSelectionException;
use Setono\SyliusCMSPlugin\Transfer\PublishedContent;
use Setono\SyliusCMSPlugin\Tree\ReferenceFinder;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Setono\SyliusCMSPlugin\Tree\TreeValues;
use Setono\SyliusCMSPlugin\Upgrade\StoredDataUpgraderInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * What an export holds: the entries, editor presets and media items someone picked, plus, as dependencies, the snippets
 * their content references and the media items it uses, whichever record uses them, and a video's poster. Entries that
 * are only linked to aren't pulled in. See "What a transfer moves" in docs/spec/transfer.md
 */
final class ExportPlannerTest extends TestCase
{
    use ProphecyTrait;

    /** @var list<EntryInterface> */
    private array $entries = [];

    /** @var list<MediaItemInterface> */
    private array $mediaItems = [];

    /** @var list<MediaFolderInterface> */
    private array $folders = [];

    /** @var list<EditorPresetInterface> */
    private array $presets = [];

    /**
     * @test
     */
    public function it_brings_the_snippets_a_picked_entry_references_and_the_media_items_they_use(): void
    {
        $image = $this->mediaItem();
        $snippet = $this->snippet([self::teaser(image: $image)]);
        $page = $this->page([self::snippetBlock($snippet)]);

        $plan = $this->plan(new ExportSelection(entries: [$page->getUuid()]));

        self::assertSame(self::uuids($page, $snippet), self::uuids(...$plan->entries()));
        self::assertSame(self::uuids($image), self::uuids(...$plan->mediaItems()));
        self::assertSame([$page->getUuid()], $plan->pickedEntries());
        self::assertSame([], $plan->pickedMediaItems());
        self::assertSame(self::uuids($snippet), self::uuids(...$plan->addedEntries()));
        self::assertSame(self::uuids($image), self::uuids(...$plan->addedMediaItems()));
    }

    /**
     * @test
     */
    public function it_brings_a_snippet_inside_a_snippet_and_the_media_items_it_uses_even_when_they_use_each_other(): void
    {
        $image = $this->mediaItem();
        $inner = $this->snippet([self::teaser(image: $image)]);
        $outer = $this->snippet([self::snippetBlock($inner)]);
        // A snippet inside itself shows nothing, but it doesn't stop an export
        $inner->setBlockTree(new StoredTree(null, [self::teaser(image: $image), self::snippetBlock($outer)]));
        $page = $this->page([self::snippetBlock($outer)]);

        $plan = $this->plan(new ExportSelection(entries: [$page->getUuid()]));

        self::assertSame(self::uuids($inner, $outer), self::uuids(...$plan->addedEntries()));
        self::assertSame(self::uuids($image), self::uuids(...$plan->addedMediaItems()));
    }

    /**
     * @test
     */
    public function it_leaves_out_entries_that_are_only_linked_to_and_references_that_do_not_resolve(): void
    {
        $linked = $this->page([]);
        $page = $this->page([
            self::teaser(readMore: $linked->getUuid()),
            new StoredBlock('0193a5be-0000-7000-8000-00000000000a', 'snippet', 1, fields: ['snippet' => '0193a5be-dead-7000-8000-000000000000']),
            new StoredBlock('0193a5be-0000-7000-8000-00000000000b', 'teaser', 1, fields: ['image' => '0193a5be-dead-7000-8000-000000000001']),
        ]);

        $plan = $this->plan(new ExportSelection(entries: [$page->getUuid()]));

        self::assertSame(self::uuids($page), self::uuids(...$plan->entries()));
        self::assertSame([], $plan->mediaItems());
    }

    /**
     * @test
     */
    public function it_brings_the_media_items_in_the_own_fields_of_a_content_type(): void
    {
        $photo = $this->mediaItem();
        $shop = new Shop();
        $shop->setPhoto($photo->getUuid());
        $this->entries[] = $shop;

        $plan = $this->plan(new ExportSelection(entries: [$shop->getUuid()]));

        self::assertSame(self::uuids($photo), self::uuids(...$plan->addedMediaItems()));
    }

    /**
     * @test
     */
    public function it_brings_the_poster_of_a_video(): void
    {
        $poster = $this->mediaItem();
        $video = $this->mediaItem(MediaKind::Video);
        $video->setPoster($poster);
        $page = $this->page([self::teaser(image: $video)]);

        $plan = $this->plan(new ExportSelection(entries: [$page->getUuid()]));

        self::assertSame(self::uuids($poster, $video), self::uuids(...$plan->addedMediaItems()));
    }

    /**
     * @test
     */
    public function it_keeps_a_picked_record_picked_when_another_picked_record_uses_it(): void
    {
        $image = $this->mediaItem();
        $snippet = $this->snippet([self::teaser(image: $image)]);
        $page = $this->page([self::snippetBlock($snippet)]);
        $folder = $this->folder('Campaigns');
        $image->setFolder($folder);

        $plan = $this->plan(new ExportSelection(entries: [$snippet->getUuid(), $page->getUuid()], mediaFolders: [$folder->getUuid()]));

        self::assertSame(self::uuids($page, $snippet), $plan->pickedEntries());
        self::assertSame(self::uuids($image), $plan->pickedMediaItems());
        self::assertSame([], $plan->addedEntries());
        self::assertSame([], $plan->addedMediaItems());
        self::assertTrue($plan->isPicked($snippet->getUuid()));
    }

    /**
     * @test
     */
    public function it_picks_every_media_item_in_a_picked_folder_and_in_its_subfolders(): void
    {
        $campaigns = $this->folder('Campaigns');
        $autumn = $this->folder('Autumn', $campaigns);
        $logos = $this->folder('Logos');
        $banner = $this->mediaItem(folder: $campaigns);
        $leaves = $this->mediaItem(folder: $autumn);
        $this->mediaItem(folder: $logos);
        $this->mediaItem();

        $plan = $this->plan(new ExportSelection(mediaFolders: [$campaigns->getUuid()]));

        self::assertSame(self::uuids($banner, $leaves), $plan->pickedMediaItems());
        self::assertSame([], $plan->entries());
    }

    /**
     * @test
     */
    public function it_brings_the_snippets_and_media_items_an_editor_preset_uses(): void
    {
        $image = $this->mediaItem();
        $snippet = $this->snippet([]);
        $preset = $this->preset([self::teaser(image: $image), self::snippetBlock($snippet)]);
        $this->preset([]);

        $plan = $this->plan(new ExportSelection(presets: [$preset->getUuid()]));

        self::assertSame([$preset->getUuid()], $plan->pickedPresets());
        self::assertSame(self::uuids($preset), self::uuids(...$plan->presets()));
        self::assertSame(self::uuids($snippet), self::uuids(...$plan->addedEntries()));
        self::assertSame(self::uuids($image), self::uuids(...$plan->addedMediaItems()));
    }

    /**
     * @test
     */
    public function it_picks_the_entries_of_a_content_type_or_every_editor_preset(): void
    {
        $first = $this->page([]);
        $second = $this->page([]);
        $this->snippet([]);
        $presets = [$this->preset([]), $this->preset([])];

        $plan = $this->plan(new ExportSelection(contentTypes: ['page'], allPresets: true));

        self::assertSame(self::uuids($first, $second), $plan->pickedEntries());
        self::assertSame(self::uuids(...$presets), $plan->pickedPresets());
    }

    /**
     * @test
     */
    public function it_picks_everything(): void
    {
        $image = $this->mediaItem();
        $snippet = $this->snippet([self::teaser(image: $image)]);
        $page = $this->page([]);
        $unused = $this->mediaItem(folder: $this->folder('Logos'));
        $preset = $this->preset([]);

        $plan = $this->plan(new ExportSelection(everything: true));

        self::assertSame(self::uuids($page, $snippet), $plan->pickedEntries());
        self::assertSame(self::uuids($preset), $plan->pickedPresets());
        self::assertSame(self::uuids($image, $unused), $plan->pickedMediaItems());
        self::assertSame([], $plan->addedEntries());
        self::assertSame([], $plan->addedMediaItems());
    }

    /**
     * @test
     */
    public function it_refuses_to_pick_an_entry_a_content_type_a_media_folder_or_an_editor_preset_there_is_none_of(): void
    {
        $unknown = '0193a5be-dead-7000-8000-000000000000';

        foreach ([
            [new ExportSelection(entries: [$unknown]), sprintf('There is no entry with the UUID %s.', $unknown)],
            [new ExportSelection(contentTypes: ['recipe']), 'There is no content type "recipe".'],
            [new ExportSelection(mediaFolders: [$unknown]), sprintf('There is no media folder with the UUID %s.', $unknown)],
            [new ExportSelection(presets: [$unknown]), sprintf('There is no editor preset with the UUID %s.', $unknown)],
        ] as [$selection, $message]) {
            try {
                $this->plan($selection);
                self::fail(sprintf('"%s" is refused', $message));
            } catch (InvalidExportSelectionException $e) {
                self::assertSame($message, $e->getMessage());
            }
        }
    }

    /**
     * @test
     */
    public function it_knows_whether_a_selection_picks_anything(): void
    {
        self::assertTrue((new ExportSelection())->isEmpty());
        self::assertFalse((new ExportSelection(allPresets: true))->isEmpty());
        self::assertFalse((new ExportSelection(everything: true))->isEmpty());
        self::assertFalse((new ExportSelection(mediaFolders: ['0193a5be-dead-7000-8000-000000000000']))->isEmpty());
    }

    private function plan(ExportSelection $selection): ExportPlan
    {
        $contentTypes = new ContentTypeRegistry([
            'page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page'],
            'snippet' => ['model' => Snippet::class, 'translation' => EntryTranslation::class, 'resource' => 'setono_sylius_cms.snippet'],
            'shop' => ['model' => Shop::class, 'translation' => ShopTranslation::class, 'resource' => 'app.shop'],
        ]);

        // Prophecy binds a promise's closure to the double, so the closures read copies of the records
        [$entries, $mediaItems, $folders, $presets] = [$this->entries, $this->mediaItems, $this->folders, $this->presets];

        $entryRepository = $this->prophesize(EntryRepositoryInterface::class);
        $entryRepository->findByUuids(Argument::any())->will(static fn (array $args): array => array_values(array_filter($entries, static fn (EntryInterface $entry): bool => in_array($entry->getUuid(), (array) $args[0], true))));
        $entryRepository->findByContentType(Argument::any())->will(static fn (array $args): array => array_values(array_filter($entries, static fn (EntryInterface $entry): bool => $contentTypes->getByEntry($entry)->code === $args[0])));

        $mediaItemRepository = $this->prophesize(MediaItemRepositoryInterface::class);
        $mediaItemRepository->findByUuids(Argument::any())->will(static fn (array $args): array => array_values(array_filter($mediaItems, static fn (MediaItemInterface $item): bool => in_array($item->getUuid(), (array) $args[0], true))));
        $mediaItemRepository->findInFolders(Argument::any())->will(static fn (array $args): array => array_values(array_filter($mediaItems, static fn (MediaItemInterface $item): bool => in_array($item->getFolder(), (array) $args[0], true))));
        $mediaItemRepository->findAllInUploadOrder()->will(static fn (): array => $mediaItems);

        $folderRepository = $this->prophesize(MediaFolderRepositoryInterface::class);
        $folderRepository->findOneByUuid(Argument::type('string'))->will(static fn (array $args): ?MediaFolderInterface => array_values(array_filter($folders, static fn (MediaFolderInterface $folder): bool => $folder->getUuid() === $args[0]))[0] ?? null);
        $folderRepository->findAllByName()->will(static fn (): array => $folders);

        $presetRepository = $this->prophesize(EditorPresetRepositoryInterface::class);
        $presetRepository->findOneByUuid(Argument::type('string'))->will(static fn (array $args): ?EditorPresetInterface => array_values(array_filter($presets, static fn (EditorPresetInterface $preset): bool => $preset->getUuid() === $args[0]))[0] ?? null);
        $presetRepository->findAllInCreationOrder()->will(static fn (): array => $presets);

        $upgrader = $this->prophesize(StoredDataUpgraderInterface::class);
        $upgrader->readBlockTree(Argument::type(EntryInterface::class))->will(static fn (array $args): StoredTree => $args[0] instanceof EntryInterface ? $args[0]->getBlockTree() : new StoredTree(null, []));

        $planner = new ExportPlanner(
            $entryRepository->reveal(),
            $presetRepository->reveal(),
            $mediaItemRepository->reveal(),
            $folderRepository->reveal(),
            $contentTypes,
            new PublishedContent($upgrader->reveal()),
            new ReferenceFinder(
                new TreeValues(new BlockTypeRegistry(['teaser' => Teaser::class, 'snippet' => \Setono\SyliusCMSPlugin\Block\Snippet::class], new FieldReader()), new LayoutRegistry([], new FieldReader())),
                new FieldHandlerLocator(new ServiceLocator([
                    EntryHandler::class => static fn (): EntryHandler => new EntryHandler($contentTypes),
                    MediaHandler::class => static fn (): MediaHandler => new MediaHandler(),
                    SnippetHandler::class => static fn (): SnippetHandler => new SnippetHandler(),
                    TextHandler::class => static fn (): TextHandler => new TextHandler(),
                    ToggleHandler::class => static fn (): ToggleHandler => new ToggleHandler(),
                ])),
            ),
        );

        return $planner->plan($selection);
    }

    /**
     * @param list<StoredBlock> $blocks
     */
    private function page(array $blocks): Page
    {
        $page = new Page();
        $page->setBlockTree(new StoredTree(null, $blocks));
        $this->entries[] = $page;

        return $page;
    }

    /**
     * @param list<StoredBlock> $blocks
     */
    private function snippet(array $blocks): Snippet
    {
        $snippet = new Snippet();
        $snippet->setBlockTree(new StoredTree(null, $blocks));
        $this->entries[] = $snippet;

        return $snippet;
    }

    private function mediaItem(MediaKind $kind = MediaKind::Image, ?MediaFolderInterface $folder = null): MediaItem
    {
        $mediaItem = new MediaItem();
        $mediaItem->setKind($kind);
        $mediaItem->setFolder($folder);
        $this->mediaItems[] = $mediaItem;

        return $mediaItem;
    }

    private function folder(string $name, ?MediaFolderInterface $parent = null): MediaFolder
    {
        $folder = new MediaFolder();
        $folder->setName($name);
        $folder->setParent($parent);
        $this->folders[] = $folder;

        return $folder;
    }

    /**
     * @param list<StoredBlock> $blocks
     */
    private function preset(array $blocks): EditorPreset
    {
        $preset = new EditorPreset();
        (new EditorPresetContent())->write($preset, new Preset('Autumn hero', $blocks, changedAt: new \DateTimeImmutable('2026-11-10T08:00:00Z')));
        $this->presets[] = $preset;

        return $preset;
    }

    private static function teaser(?MediaItemInterface $image = null, ?string $readMore = null): StoredBlock
    {
        return new StoredBlock(self::key(), 'teaser', 1, fields: array_filter(['image' => $image?->getUuid(), 'readMore' => $readMore], static fn (?string $value): bool => null !== $value));
    }

    private static function snippetBlock(Snippet $snippet): StoredBlock
    {
        return new StoredBlock(self::key(), 'snippet', 1, fields: ['snippet' => $snippet->getUuid()]);
    }

    private static function key(): string
    {
        return \Symfony\Component\Uid\Uuid::v7()->toRfc4122();
    }

    /**
     * The records' UUIDs, sorted, as a plan lists them
     *
     * @return list<string>
     */
    private static function uuids(EntryInterface|MediaItemInterface|EditorPresetInterface ...$records): array
    {
        $uuids = array_map(static fn (EntryInterface|MediaItemInterface|EditorPresetInterface $record): string => $record->getUuid(), $records);
        sort($uuids, \SORT_STRING);

        return $uuids;
    }
}
