<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Transfer\Import;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Block\Snippet as SnippetBlock;
use Setono\SyliusCMSPlugin\EntryFormat\EntryNormalizer;
use Setono\SyliusCMSPlugin\Field\EntryHandler;
use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Field\MediaHandler;
use Setono\SyliusCMSPlugin\Field\SnippetHandler;
use Setono\SyliusCMSPlugin\Localization\Availability;
use Setono\SyliusCMSPlugin\Media\CheckedFile;
use Setono\SyliusCMSPlugin\Media\MediaKind;
use Setono\SyliusCMSPlugin\Model\EditorPreset;
use Setono\SyliusCMSPlugin\Model\EditorPresetInterface;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\EntryTranslation;
use Setono\SyliusCMSPlugin\Model\Homepage;
use Setono\SyliusCMSPlugin\Model\HomepageInterface;
use Setono\SyliusCMSPlugin\Model\MediaFolder;
use Setono\SyliusCMSPlugin\Model\MediaFolderInterface;
use Setono\SyliusCMSPlugin\Model\MediaItem;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Model\Placement;
use Setono\SyliusCMSPlugin\Model\PlacementInterface;
use Setono\SyliusCMSPlugin\Model\ScheduledPublishInterface;
use Setono\SyliusCMSPlugin\Model\Snippet;
use Setono\SyliusCMSPlugin\Preset\EditorPresetContent;
use Setono\SyliusCMSPlugin\Preset\Preset;
use Setono\SyliusCMSPlugin\Publishing\EntrySettings;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyManagerInterface;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistry;
use Setono\SyliusCMSPlugin\Rendering\ReferenceTypeLoaderInterface;
use Setono\SyliusCMSPlugin\Repository\EditorPresetRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\HomepageRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\MediaFolderRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\MediaItemRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\PlacementRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\ScheduledPublishRepositoryInterface;
use Setono\SyliusCMSPlugin\Routing\UrlClaim;
use Setono\SyliusCMSPlugin\Routing\UrlClash;
use Setono\SyliusCMSPlugin\Routing\UrlClashFinderInterface;
use Setono\SyliusCMSPlugin\Targeting\Audience;
use Setono\SyliusCMSPlugin\Targeting\DateWindow;
use Setono\SyliusCMSPlugin\Tests\Support\Conditions;
use Setono\SyliusCMSPlugin\Tests\Unit\Transfer\Fixture\Teaser;
use Setono\SyliusCMSPlugin\Transfer\Format\ExportedEntry;
use Setono\SyliusCMSPlugin\Transfer\Format\ExportedMediaFolder;
use Setono\SyliusCMSPlugin\Transfer\Format\ExportedMediaItem;
use Setono\SyliusCMSPlugin\Transfer\Format\ExportedPlacement;
use Setono\SyliusCMSPlugin\Transfer\Format\Manifest;
use Setono\SyliusCMSPlugin\Transfer\Import\HomepageChange;
use Setono\SyliusCMSPlugin\Transfer\Import\ImportAction;
use Setono\SyliusCMSPlugin\Transfer\Import\ImportConflict;
use Setono\SyliusCMSPlugin\Transfer\Import\ImportPlan;
use Setono\SyliusCMSPlugin\Transfer\Import\ImportPlanner;
use Setono\SyliusCMSPlugin\Transfer\Import\ImportRefusal;
use Setono\SyliusCMSPlugin\Transfer\Import\PlannedRecord;
use Setono\SyliusCMSPlugin\Transfer\Import\ReadEntry;
use Setono\SyliusCMSPlugin\Transfer\Import\ReadExport;
use Setono\SyliusCMSPlugin\Transfer\Import\ReadMediaItem;
use Setono\SyliusCMSPlugin\Transfer\Import\ReadPreset;
use Setono\SyliusCMSPlugin\Transfer\Import\RecordKind;
use Setono\SyliusCMSPlugin\Transfer\Import\TargetRecords;
use Setono\SyliusCMSPlugin\Transfer\Import\UnresolvedReference;
use Setono\SyliusCMSPlugin\Transfer\Import\UnresolvedReferences;
use Setono\SyliusCMSPlugin\Transfer\PublishedContent;
use Setono\SyliusCMSPlugin\Tree\ReferenceFinder;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Setono\SyliusCMSPlugin\Tree\TreeValues;
use Setono\SyliusCMSPlugin\Upgrade\StoredDataUpgraderInterface;
use Setono\SyliusCMSPlugin\Validation\EmbedCodeValidator;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Customer\Model\CustomerGroupInterface;
use Sylius\Component\Locale\Model\Locale;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Component\Product\Model\ProductAttributeInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Translation\IdentityTranslator;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * Step 2 of an import, the plan: matched by UUID only, what it creates, updates, leaves unchanged or alone, the
 * conflicts, the reasons it refuses, the homepage changes and the references that don't resolve. A record identical to
 * the target's is unchanged before the stale check, so it's never a conflict. See "What an import does", "Conflicts",
 * "Stale records" and "Code the target must have" in docs/spec/transfer.md
 */
final class ImportPlannerTest extends TestCase
{
    use ProphecyTrait;

    /** When the export's records last changed in the source */
    private const EXPORTED = '2026-11-12T10:15:00Z';

    /** Before the export's copy */
    private const EARLIER = '2026-11-01T08:00:00Z';

    /** After the export's copy */
    private const LATER = '2026-11-14T09:30:00Z';

    private const PAGE = '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e';

    private const OTHER_PAGE = '0193a5be-71b1-7a3c-9d4e-5f6a7b8c9d0e';

    private const THIRD_PAGE = '0193a5be-71b2-7a3c-9d4e-5f6a7b8c9d0e';

    private const FOURTH_PAGE = '0193a5be-71b3-7a3c-9d4e-5f6a7b8c9d0e';

    private const SNIPPET = '0193a5be-7255-7f00-8a1b-2c3d4e5f6a7b';

    private const INNER_SNIPPET = '0193a5be-7256-7a01-8b2c-3d4e5f6a7b8c';

    private const PRESET = '0193a5be-7400-7d3e-8f4a-5b6c7d8e9f0a';

    private const BANNER = '0193a5be-70aa-7c11-8e2d-1b2c3d4e5f60';

    private const CAMPAIGNS = '0193a5be-7500-7e4f-9a5b-6c7d8e9f0a1b';

    private const AUTUMN = '0193a5be-7501-7f5a-8b6c-7d8e9f0a1b2c';

    private const GONE = '0193a5be-dead-7000-8000-000000000000';

    private const EMBED = '0193a5be-0000-7000-8000-0000000000e1';

    private const CHECKSUM = '4b227777d4dd1fc61c6f884f48641d02b4d121d3fd328cb08b5531fcacdabf8a';

    /** @var array<string, EntryInterface> what the target holds, by UUID */
    private array $targetEntries = [];

    /** @var array<string, EditorPresetInterface> */
    private array $targetPresets = [];

    /** @var array<string, MediaItemInterface> */
    private array $targetMediaItems = [];

    /** @var array<string, MediaFolderInterface> */
    private array $targetFolders = [];

    /** @var list<string> the UUIDs of the target's entries that have a draft */
    private array $drafts = [];

    /** @var list<string> the UUIDs of the target's entries that have a scheduled publish */
    private array $scheduledPublishes = [];

    /** @var array<string, list<PlacementInterface>> the target's placements, by their snippet's UUID */
    private array $placements = [];

    /** @var list<HomepageInterface> */
    private array $homepages = [];

    /** @var array<string, list<UrlClash>> the URLs other entries have published in the target, by the entry's UUID */
    private array $publishedClashes = [];

    /** @var list<array{string, array<array-key, mixed>, array<array-key, mixed>}> each entry whose URLs the plan looked up, with its slugs and channels */
    private array $urlLookups = [];

    /**
     * @test
     */
    public function it_creates_the_entries_presets_and_media_items_the_target_lacks(): void
    {
        $plan = $this->plan(self::export(
            [self::entry(self::page(self::PAGE, 'Autumn sale')), self::entry(self::snippet(self::SNIPPET, 'Autumn banner'), picked: false)],
            [self::preset('Autumn hero')],
            [self::mediaItem()],
        ));

        self::assertEquals([
            new PlannedRecord(RecordKind::Entry, self::PAGE, 'Autumn sale', true, ImportAction::Create, 'page'),
            new PlannedRecord(RecordKind::Entry, self::SNIPPET, 'Autumn banner', false, ImportAction::Create, 'snippet'),
        ], $plan->entries);
        self::assertEquals([new PlannedRecord(RecordKind::Preset, self::PRESET, 'Autumn hero', true, ImportAction::Create)], $plan->presets);
        self::assertEquals([new PlannedRecord(RecordKind::MediaItem, self::BANNER, 'Autumn banner', true, ImportAction::Create)], $plan->mediaItems);
        self::assertSame([], $plan->conflicts);
        self::assertSame([], $plan->refusals);
        self::assertSame([], $plan->homepageChanges);
    }

    /**
     * @test
     */
    public function it_marks_an_entry_identical_to_the_targets_unchanged_before_it_checks_for_conflicts(): void
    {
        // The target changed it later and has a draft and a scheduled publish of it, but its content, its on-save
        // settings and its placements are the export's
        $placement = new ExportedPlacement('0193a5be-7300-7b1c-8d2e-3f4a5b6c7d8e', 'product.after_price', [], ['0193a5be-7301-7c2d-9e3f-4a5b6c7d8e9f']);
        $this->inTarget(self::snippet(self::SNIPPET, 'Autumn banner'), self::LATER, draft: true, scheduled: true, placements: [$placement]);

        $plan = $this->plan(self::export([self::entry(self::snippet(self::SNIPPET, 'Autumn banner'), placements: [$placement])]));

        self::assertSame([[self::SNIPPET, ImportAction::Unchanged]], self::actions($plan->entries));
        self::assertSame([], $plan->conflicts);
        self::assertSame([], $plan->refusals);
    }

    /**
     * @test
     */
    public function it_updates_a_picked_entry_whose_content_settings_or_placements_differ_from_the_targets(): void
    {
        $this->inTarget(self::page(self::PAGE, 'Autumn sale'), self::EARLIER);
        $this->inTarget(self::page(self::OTHER_PAGE, 'Spring sale'), self::EARLIER);
        $this->inTarget(self::snippet(self::SNIPPET, 'Autumn banner'), self::EARLIER);

        $plan = $this->plan(self::export([
            self::entry(self::page(self::PAGE, 'Autumn sale!')),
            self::entry(self::page(self::OTHER_PAGE, 'Spring sale'), new EntrySettings(false, new DateWindow(), [], Audience::everyone())),
            self::entry(self::snippet(self::SNIPPET, 'Autumn banner'), placements: [new ExportedPlacement('0193a5be-7300-7b1c-8d2e-3f4a5b6c7d8e', 'product.after_price', [], [])]),
        ]));

        self::assertSame([
            [self::PAGE, ImportAction::Update],
            [self::OTHER_PAGE, ImportAction::Update],
            [self::SNIPPET, ImportAction::Update],
        ], self::actions($plan->entries));
        self::assertSame([], $plan->conflicts);
    }

    /**
     * @test
     */
    public function it_lists_a_draft_a_scheduled_publish_and_a_later_change_in_the_target_as_conflicts_of_a_picked_entry(): void
    {
        $this->inTarget(self::page(self::PAGE, 'Autumn sale'), self::LATER, draft: true, scheduled: true);

        $plan = $this->plan(self::export([self::entry(self::page(self::PAGE, 'Autumn sale!'))]));

        self::assertSame([[self::PAGE, ImportAction::Update]], self::actions($plan->entries));
        self::assertSame([
            ['draft', self::PAGE, 'setono_sylius_cms.import.conflict.draft', ['%title%' => 'Autumn sale!']],
            ['scheduled_publish', self::PAGE, 'setono_sylius_cms.import.conflict.scheduled_publish', ['%title%' => 'Autumn sale!']],
            ['stale', self::PAGE, 'setono_sylius_cms.import.conflict.stale', ['%title%' => 'Autumn sale!', '%changed_at%' => self::LATER, '%exported_at%' => self::EXPORTED]],
        ], self::conflicts($plan->conflicts));
        self::assertTrue($plan->isRefusedOnlyForConflictsOverwritingResolves());
    }

    /**
     * @test
     */
    public function it_leaves_alone_a_dependency_the_target_has_otherwise_without_a_conflict(): void
    {
        // The target's own edits to a shared snippet survive
        $this->inTarget(self::snippet(self::SNIPPET, 'Our banner'), self::LATER, draft: true, scheduled: true);

        $plan = $this->plan(self::export([self::entry(self::snippet(self::SNIPPET, 'Autumn banner'), picked: false)]));

        self::assertEquals([new PlannedRecord(RecordKind::Entry, self::SNIPPET, 'Autumn banner', false, ImportAction::LeaveAlone, 'snippet')], $plan->entries);
        self::assertSame([], $plan->conflicts);
    }

    /**
     * @test
     */
    public function it_refuses_an_entry_whose_uuid_is_an_entry_of_another_content_type_in_the_target(): void
    {
        $this->inTarget(self::snippet(self::PAGE, 'A snippet'), self::EARLIER);

        $plan = $this->plan(self::export([self::entry(self::page(self::PAGE, 'Autumn sale')), self::entry(self::page(self::OTHER_PAGE, 'Spring sale'))]));

        self::assertSame([[self::PAGE, 'setono_sylius_cms.import.other_content_type', ['%title%' => 'Autumn sale', '%content_type%' => 'snippet']]], self::refusals($plan->refusals));
        self::assertSame([[self::OTHER_PAGE, ImportAction::Create]], self::actions($plan->entries));
        self::assertTrue($plan->isRefused(true));
    }

    /**
     * @test
     */
    public function it_keeps_the_reasons_to_refuse_that_reading_the_export_found(): void
    {
        $unknown = new ImportRefusal(self::PAGE, new TranslatableMessage('setono_sylius_cms.import.unknown_layout', ['%title%' => 'Autumn sale', '%layout%' => 'standard']));

        $plan = $this->plan(self::export([self::entry(self::page(self::OTHER_PAGE, 'Spring sale'))], refusals: [$unknown]));

        self::assertSame([$unknown], $plan->refusals);
    }

    /**
     * @test
     */
    public function it_plans_the_snippets_an_entry_shows_before_it_and_otherwise_keeps_the_exports_order(): void
    {
        $plan = $this->plan(self::export([
            // It only links to the other page, which doesn't need to come first
            self::entry(self::page(self::PAGE, 'Autumn sale', blocks: [self::snippetBlock(self::SNIPPET), self::teaser(readMore: self::OTHER_PAGE)])),
            self::entry(self::page(self::OTHER_PAGE, 'Spring sale')),
            // Snippets inside each other don't stop the plan
            self::entry(self::snippet(self::SNIPPET, 'Autumn banner', [self::snippetBlock(self::INNER_SNIPPET)]), picked: false),
            self::entry(self::snippet(self::INNER_SNIPPET, 'Newsletter signup', [self::snippetBlock(self::SNIPPET)]), picked: false),
        ]));

        self::assertSame([self::INNER_SNIPPET, self::SNIPPET, self::PAGE, self::OTHER_PAGE], array_map(static fn (PlannedRecord $entry): string => $entry->uuid, $plan->entries));
    }

    /**
     * @test
     */
    public function it_lists_a_url_clash_with_an_entry_published_in_the_target_that_the_import_does_not_write(): void
    {
        $page = self::page(self::PAGE, 'Autumn sale', 'autumn-sale');
        // A slug in a locale without a title has no URL
        $page->setCurrentLocale('da_DK');
        $page->setSlug('efteraarsudsalg');
        $this->publishedClashes[self::PAGE] = [
            new UrlClash('en_US', 'autumn-sale', new UrlClaim(self::OTHER_PAGE, 'page', 'en_US', 'autumn-sale', 'Old sale', ['WEB', 'MOBILE']), ['WEB']),
            new UrlClash('en_US', 'autumn-sale', new UrlClaim(self::GONE, 'page', 'en_US', 'autumn-sale', null, ['WEB']), ['WEB']),
        ];

        $plan = $this->plan(self::export([self::entry($page, self::settings(channels: ['WEB']))]));

        self::assertSame([[self::PAGE, ['en_US' => 'autumn-sale'], ['WEB']]], $this->urlLookups);
        self::assertSame([
            ['url_clash', self::PAGE, 'setono_sylius_cms.import.conflict.url_clash', ['%title%' => 'Autumn sale', '%url%' => '/en_US/autumn-sale', '%other%' => 'Old sale', '%channels%' => 'WEB']],
            ['url_clash', self::PAGE, 'setono_sylius_cms.import.conflict.url_clash', ['%title%' => 'Autumn sale', '%url%' => '/en_US/autumn-sale', '%other%' => self::GONE, '%channels%' => 'WEB']],
        ], self::conflicts($plan->conflicts));
        self::assertTrue($plan->isRefused(true), 'Overwriting doesn\'t resolve a URL clash');
    }

    /**
     * @test
     */
    public function it_lists_a_url_clash_between_two_entries_it_writes_once_on_the_one_written_later(): void
    {
        // Published in the target, but the import writes it with the slug it has in the export
        $this->publishedClashes[self::OTHER_PAGE] = [new UrlClash('en_US', 'sale', new UrlClaim(self::PAGE, 'page', 'en_US', 'sale', 'Autumn sale', ['WEB']), ['WEB'])];

        $plan = $this->plan(self::export([
            self::entry(self::page(self::PAGE, 'Autumn sale', 'sale'), self::settings(channels: ['MOBILE', 'WEB'])),
            self::entry(self::page(self::OTHER_PAGE, 'Spring sale', 'sale'), self::settings(channels: ['B2B', 'MOBILE', 'WEB'])),
            // No channel in common
            self::entry(self::page(self::THIRD_PAGE, 'B2B sale', 'sale'), self::settings(channels: ['WHOLESALE'])),
            // A snippet has no URL of its own
            self::entry(self::snippet(self::SNIPPET, 'Sale banner', slug: 'sale'), self::settings(channels: ['WEB'])),
        ]));

        self::assertSame([
            ['url_clash', self::OTHER_PAGE, 'setono_sylius_cms.import.conflict.url_clash', ['%title%' => 'Spring sale', '%url%' => '/en_US/sale', '%other%' => 'Autumn sale', '%channels%' => 'MOBILE, WEB']],
        ], self::conflicts($plan->conflicts));
        self::assertSame([self::PAGE, self::OTHER_PAGE, self::THIRD_PAGE], array_map(static fn (array $lookup): string => $lookup[0], $this->urlLookups));
    }

    /**
     * @test
     */
    public function it_looks_for_url_clashes_only_of_the_entries_it_writes(): void
    {
        $this->inTarget(self::page(self::PAGE, 'Autumn sale', 'autumn-sale'), self::EARLIER);
        // The target already has this URL, so the import leaves it as it is
        $this->publishedClashes[self::PAGE] = [new UrlClash('en_US', 'autumn-sale', new UrlClaim(self::OTHER_PAGE, 'page', 'en_US', 'autumn-sale', 'Old sale', ['WEB']), ['WEB'])];

        $plan = $this->plan(self::export([self::entry(self::page(self::PAGE, 'Autumn sale', 'autumn-sale'))]));

        self::assertSame([[self::PAGE, ImportAction::Unchanged]], self::actions($plan->entries));
        self::assertSame([], $plan->conflicts);
        self::assertSame([], $this->urlLookups);
    }

    /**
     * @test
     */
    public function it_refuses_an_embed_code_block_while_embed_code_is_off_unless_the_target_published_it_with_the_same_code(): void
    {
        // A new entry, an entry never published in the target, and one whose published block has other code
        $this->inTarget(self::page(self::OTHER_PAGE, 'Spring sale', blocks: [self::embedCode('<script>a</script>')]), self::EARLIER);
        $changed = self::page(self::THIRD_PAGE, 'Summer sale', blocks: [self::embedCode('<script>a</script>')]);
        $changed->publish(new \DateTimeImmutable(self::EARLIER));
        $this->inTarget($changed, self::EARLIER);
        // Published in the target with the same key and code, and moved or not, it may come in
        $same = self::page(self::FOURTH_PAGE, 'Winter sale', blocks: [self::embedCode('<script>a</script>')]);
        $same->publish(new \DateTimeImmutable(self::EARLIER));
        $this->inTarget($same, self::EARLIER);

        $export = self::export([
            self::entry(self::page(self::PAGE, 'Autumn sale', blocks: [self::embedCode('<script>a</script>')])),
            self::entry(self::page(self::OTHER_PAGE, 'Spring sale!', blocks: [self::embedCode('<script>a</script>')])),
            self::entry(self::page(self::THIRD_PAGE, 'Summer sale!', blocks: [self::embedCode('<script>b</script>')])),
            self::entry(self::page(self::FOURTH_PAGE, 'Winter sale!', blocks: [self::teaser(children: [self::embedCode('<script>a</script>')])])),
        ]);

        self::assertSame([
            [self::PAGE, 'setono_sylius_cms.import.embed_code', ['%title%' => 'Autumn sale']],
            [self::OTHER_PAGE, 'setono_sylius_cms.import.embed_code', ['%title%' => 'Spring sale!']],
            [self::THIRD_PAGE, 'setono_sylius_cms.import.embed_code', ['%title%' => 'Summer sale!']],
        ], self::refusals($this->plan($export)->refusals));
        self::assertSame([], $this->plan($export, embedCode: true)->refusals);
    }

    /**
     * @test
     */
    public function it_lists_the_channels_whose_homepage_an_imported_page_becomes_or_stops_being(): void
    {
        $target = self::page(self::PAGE, 'Autumn sale');
        $target->addHomepage(new Homepage($target, 'MOBILE'));
        $target->addHomepage(new Homepage($target, 'B2B'));
        $this->inTarget($target, self::EARLIER);
        $current = self::page(self::OTHER_PAGE, 'Old home');
        $this->homepages = [new Homepage($current, 'WEB'), new Homepage($target, 'MOBILE'), new Homepage($target, 'B2B')];

        $plan = $this->plan(self::export([self::entry(self::page(self::PAGE, 'Autumn sale'), self::settings(homepageOf: ['MOBILE', 'WEB', 'WHOLESALE']))]));

        self::assertEquals([
            new HomepageChange('WEB', self::OTHER_PAGE, 'Old home', self::PAGE, 'Autumn sale'),
            // A channel without a homepage has Sylius's own
            new HomepageChange('WHOLESALE', null, null, self::PAGE, 'Autumn sale'),
            new HomepageChange('B2B', self::PAGE, 'Autumn sale', null, null),
        ], $plan->homepageChanges);
    }

    /**
     * @test
     */
    public function it_changes_no_homepage_for_a_page_it_leaves_alone(): void
    {
        $this->inTarget(self::page(self::PAGE, 'Our home'), self::EARLIER);

        $plan = $this->plan(self::export([self::entry(self::page(self::PAGE, 'Autumn sale'), self::settings(homepageOf: ['WEB']), picked: false)]));

        self::assertSame([[self::PAGE, ImportAction::LeaveAlone]], self::actions($plan->entries));
        self::assertSame([], $plan->homepageChanges);
    }

    /**
     * @test
     */
    public function it_plans_editor_presets_as_it_plans_entries(): void
    {
        $this->targetPresets[self::PRESET] = self::targetPreset(self::PRESET, 'Autumn hero', self::LATER);
        $unchanged = '0193a5be-7401-7d3e-8f4a-5b6c7d8e9f0a';
        $updated = '0193a5be-7402-7d3e-8f4a-5b6c7d8e9f0a';
        $leftAlone = '0193a5be-7403-7d3e-8f4a-5b6c7d8e9f0a';
        $this->targetPresets[$unchanged] = self::targetPreset($unchanged, 'Spring hero', self::LATER);
        $this->targetPresets[$updated] = self::targetPreset($updated, 'Summer hero', self::EARLIER);
        $this->targetPresets[$leftAlone] = self::targetPreset($leftAlone, 'Our hero', self::LATER);

        $plan = $this->plan(self::export([], [
            self::preset('Autumn hero!'),
            self::preset('Spring hero', $unchanged),
            self::preset('Summer hero!', $updated),
            self::preset('Winter hero', $leftAlone, picked: false),
        ]));

        self::assertEquals([
            new PlannedRecord(RecordKind::Preset, self::PRESET, 'Autumn hero!', true, ImportAction::Update),
            new PlannedRecord(RecordKind::Preset, $unchanged, 'Spring hero', true, ImportAction::Unchanged),
            new PlannedRecord(RecordKind::Preset, $updated, 'Summer hero!', true, ImportAction::Update),
            new PlannedRecord(RecordKind::Preset, $leftAlone, 'Winter hero', false, ImportAction::LeaveAlone),
        ], $plan->presets);
        self::assertSame([
            ['stale', self::PRESET, 'setono_sylius_cms.import.conflict.stale', ['%title%' => 'Autumn hero!', '%changed_at%' => self::LATER, '%exported_at%' => self::EXPORTED]],
        ], self::conflicts($plan->conflicts));
    }

    /**
     * @test
     */
    public function it_plans_media_items_as_it_plans_entries(): void
    {
        $unchanged = '0193a5be-70ab-7c12-8e2d-1b2c3d4e5f61';
        $stale = '0193a5be-70ac-7c13-8e2d-1b2c3d4e5f62';
        $leftAlone = '0193a5be-70ad-7c14-8e2d-1b2c3d4e5f63';
        $this->targetMediaItems[self::BANNER] = self::targetMediaItem(self::BANNER, 'Old banner', self::EARLIER);
        // The same file and metadata, whenever it changed
        $this->targetMediaItems[$unchanged] = self::targetMediaItem($unchanged, 'Autumn banner', self::LATER);
        $this->targetMediaItems[$stale] = self::targetMediaItem($stale, 'Our banner', self::LATER);
        $this->targetMediaItems[$leftAlone] = self::targetMediaItem($leftAlone, 'Our logo', self::LATER);

        $plan = $this->plan(self::export([], [], [
            self::mediaItem(),
            self::mediaItem($unchanged),
            self::mediaItem($stale),
            self::mediaItem($leftAlone, picked: false),
        ]));

        self::assertSame([
            [self::BANNER, ImportAction::Update],
            [$unchanged, ImportAction::Unchanged],
            [$stale, ImportAction::Update],
            [$leftAlone, ImportAction::LeaveAlone],
        ], self::actions($plan->mediaItems));
        self::assertSame([
            ['stale', $stale, 'setono_sylius_cms.import.conflict.stale', ['%title%' => 'Autumn banner', '%changed_at%' => self::LATER, '%exported_at%' => self::EXPORTED]],
        ], self::conflicts($plan->conflicts));
    }

    /**
     * @test
     */
    public function it_refuses_a_file_that_would_replace_a_media_item_of_another_kind(): void
    {
        $video = self::targetMediaItem(self::BANNER, 'Lookbook', self::EARLIER);
        $video->setKind(MediaKind::Video);
        $this->targetMediaItems[self::BANNER] = $video;

        $plan = $this->plan(self::export([], [], [self::mediaItem()]));

        self::assertSame([], $plan->mediaItems);
        self::assertCount(1, $plan->refusals);
        self::assertSame(self::BANNER, $plan->refusals[0]->uuid);
        $reason = $plan->refusals[0]->reason;
        self::assertInstanceOf(TranslatableMessage::class, $reason);
        self::assertSame('setono_sylius_cms.import.media_refused', $reason->getMessage());
        self::assertSame('Autumn banner', $reason->getParameters()['%name%']);
        $why = $reason->getParameters()['%reason%'];
        self::assertInstanceOf(TranslatableMessage::class, $why);
        self::assertSame('setono_sylius_cms.media.refused.not_video', $why->getMessage());
        self::assertSame(['%filename%' => 'autumn-banner.jpg'], $why->getParameters());
    }

    /**
     * @test
     */
    public function it_creates_the_folders_of_the_media_items_it_writes_that_the_target_lacks_and_never_moves_or_renames_one(): void
    {
        $campaigns = new MediaFolder();
        $campaigns->setUuid(self::CAMPAIGNS);
        $campaigns->setName('Campaigns');
        $this->targetFolders[self::CAMPAIGNS] = $campaigns;
        $folders = [new ExportedMediaFolder(self::CAMPAIGNS, 'Campaigns', null), new ExportedMediaFolder(self::AUTUMN, 'Autumn', self::CAMPAIGNS)];

        $plan = $this->plan(self::export([], [], [self::mediaItem(folder: self::AUTUMN)], $folders));

        self::assertEquals([
            new PlannedRecord(RecordKind::MediaFolder, self::CAMPAIGNS, 'Campaigns', false, ImportAction::Unchanged),
            new PlannedRecord(RecordKind::MediaFolder, self::AUTUMN, 'Autumn', false, ImportAction::Create),
        ], $plan->mediaFolders);

        // Renamed, or under another parent, in the target
        $campaigns->setName('Old campaigns');
        $autumn = new MediaFolder();
        $autumn->setUuid(self::AUTUMN);
        $autumn->setName('Autumn');
        $this->targetFolders[self::AUTUMN] = $autumn;

        self::assertSame([
            [self::CAMPAIGNS, ImportAction::LeaveAlone],
            [self::AUTUMN, ImportAction::LeaveAlone],
        ], self::actions($this->plan(self::export([], [], [self::mediaItem(folder: self::AUTUMN)], $folders))->mediaFolders));
    }

    /**
     * @test
     */
    public function it_plans_no_folders_for_media_items_it_does_not_write(): void
    {
        $this->targetMediaItems[self::BANNER] = self::targetMediaItem(self::BANNER, 'Our banner', self::LATER);

        $plan = $this->plan(self::export([], [], [self::mediaItem(folder: self::AUTUMN, picked: false)], [new ExportedMediaFolder(self::AUTUMN, 'Autumn', null)]));

        self::assertSame([[self::BANNER, ImportAction::LeaveAlone]], self::actions($plan->mediaItems));
        self::assertSame([], $plan->mediaFolders);
    }

    /**
     * @test
     */
    public function it_lists_the_unresolved_references_of_the_records_it_writes_only(): void
    {
        $this->inTarget(self::page(self::OTHER_PAGE, 'Our sale'), self::EARLIER);

        $plan = $this->plan(self::export([
            self::entry(self::page(self::PAGE, 'Autumn sale', blocks: [self::snippetBlock(self::GONE)]), self::settings(channels: ['GONE_WEB', 'WEB'])),
            self::entry(self::page(self::OTHER_PAGE, 'Spring sale', blocks: [self::snippetBlock(self::GONE)]), picked: false),
        ], [
            self::preset('Autumn hero', blocks: [self::teaser(image: self::GONE)]),
        ], [
            self::mediaItem(alt: ['en_US' => 'A woman in a red coat', 'xx_XX' => 'Eine Frau']),
        ]));

        self::assertEquals([
            new UnresolvedReference(self::PAGE, 'Autumn sale', 'entry', self::GONE),
            new UnresolvedReference(self::PAGE, 'Autumn sale', 'channel', 'GONE_WEB'),
            new UnresolvedReference(self::PRESET, 'Autumn hero', 'media_item', self::GONE),
            new UnresolvedReference(self::BANNER, 'Autumn banner', 'locale', 'xx_XX'),
        ], $plan->unresolvedReferences);
    }

    private function plan(ReadExport $export, bool $embedCode = false): ImportPlan
    {
        $contentTypes = self::contentTypes();

        // Prophecy binds a promise's closure to the double, so the closures read copies of what the target holds
        [$entries, $presets, $mediaItems, $folders] = [$this->targetEntries, $this->targetPresets, $this->targetMediaItems, $this->targetFolders];
        [$drafts, $scheduledPublishes, $placements, $homepages, $publishedClashes] = [$this->drafts, $this->scheduledPublishes, $this->placements, $this->homepages, $this->publishedClashes];

        $entryRepository = $this->prophesize(EntryRepositoryInterface::class);
        $entryRepository->findOneByUuid(Argument::type('string'))->will(static fn (array $args): ?EntryInterface => is_string($args[0]) ? $entries[$args[0]] ?? null : null);
        $presetRepository = $this->prophesize(EditorPresetRepositoryInterface::class);
        $presetRepository->findOneByUuid(Argument::type('string'))->will(static fn (array $args): ?EditorPresetInterface => is_string($args[0]) ? $presets[$args[0]] ?? null : null);
        $mediaItemRepository = $this->prophesize(MediaItemRepositoryInterface::class);
        $mediaItemRepository->findOneByUuid(Argument::type('string'))->will(static fn (array $args): ?MediaItemInterface => is_string($args[0]) ? $mediaItems[$args[0]] ?? null : null);
        $folderRepository = $this->prophesize(MediaFolderRepositoryInterface::class);
        $folderRepository->findOneByUuid(Argument::type('string'))->will(static fn (array $args): ?MediaFolderInterface => is_string($args[0]) ? $folders[$args[0]] ?? null : null);

        // The trees are the code's versions already
        $upgrader = $this->prophesize(StoredDataUpgraderInterface::class);
        $upgrader->readBlockTree(Argument::type(EntryInterface::class))->will(static fn (array $args): StoredTree => $args[0] instanceof EntryInterface ? $args[0]->getBlockTree() : new StoredTree(null, []));

        $workingCopies = $this->prophesize(WorkingCopyManagerInterface::class);
        $workingCopies->hasDraft(Argument::type(EntryInterface::class))->will(static fn (array $args): bool => $args[0] instanceof EntryInterface && in_array($args[0]->getUuid(), $drafts, true));
        $scheduled = $this->prophesize(ScheduledPublishInterface::class)->reveal();
        $scheduledPublishRepository = $this->prophesize(ScheduledPublishRepositoryInterface::class);
        $scheduledPublishRepository->findOneByEntry(Argument::type(EntryInterface::class))->will(static fn (array $args): ?ScheduledPublishInterface => $args[0] instanceof EntryInterface && in_array($args[0]->getUuid(), $scheduledPublishes, true) ? $scheduled : null);
        $placementRepository = $this->prophesize(PlacementRepositoryInterface::class);
        $placementRepository->findBySnippet(Argument::type(EntryInterface::class))->will(static fn (array $args): array => $args[0] instanceof EntryInterface ? $placements[$args[0]->getUuid()] ?? [] : []);

        $urlClashes = $this->prophesize(UrlClashFinderInterface::class);
        $urlLookups = &$this->urlLookups;
        $urlClashes->findPublished(Argument::type(EntryInterface::class), Argument::type('array'), Argument::type('array'))->will(static function (array $args) use ($publishedClashes, &$urlLookups): array {
            [$entry, $slugs, $channels] = $args;
            \assert($entry instanceof EntryInterface && is_array($slugs) && is_array($channels));
            $urlLookups[] = [$entry->getUuid(), $slugs, $channels];

            return $publishedClashes[$entry->getUuid()] ?? [];
        });

        $homepageRepository = $this->prophesize(HomepageRepositoryInterface::class);
        $homepageRepository->findByChannelCodes(Argument::type('array'))->will(static fn (array $args): array => array_values(array_filter($homepages, static fn (HomepageInterface $homepage): bool => in_array($homepage->getChannelCode(), (array) $args[0], true))));

        $referenceFinder = new ReferenceFinder(
            new TreeValues(new BlockTypeRegistry(['teaser' => Teaser::class, 'snippet' => SnippetBlock::class], new FieldReader()), new LayoutRegistry([], new FieldReader())),
            new FieldHandlerLocator(new ServiceLocator([
                EntryHandler::class => static fn (): EntryHandler => new EntryHandler($contentTypes),
                MediaHandler::class => static fn (): MediaHandler => new MediaHandler(),
                SnippetHandler::class => static fn (): SnippetHandler => new SnippetHandler(),
            ])),
        );

        return (new ImportPlanner(
            $entryRepository->reveal(),
            $presetRepository->reveal(),
            $contentTypes,
            new TargetRecords(new EntryNormalizer($contentTypes), new PublishedContent($upgrader->reveal()), $workingCopies->reveal(), $scheduledPublishRepository->reveal(), $placementRepository->reveal()),
            $urlClashes->reveal(),
            new Availability(),
            new EmbedCodeValidator($embedCode, new IdentityTranslator()),
            $upgrader->reveal(),
            $homepageRepository->reveal(),
            $this->unresolvedReferences($referenceFinder, array_keys($entries), array_keys($mediaItems)),
            $referenceFinder,
            $mediaItemRepository->reveal(),
            $folderRepository->reveal(),
        ))->plan($export);
    }

    /**
     * Finds what doesn't resolve in a target that has the channels WEB and MOBILE, the locale en_US, and its own entries
     * and media items
     *
     * @param list<string> $entries
     * @param list<string> $mediaItems
     */
    private function unresolvedReferences(ReferenceFinder $referenceFinder, array $entries, array $mediaItems): UnresolvedReferences
    {
        $loaders = [];
        foreach (['entry' => $entries, 'media_item' => $mediaItems] as $type => $existing) {
            $loader = $this->prophesize(ReferenceTypeLoaderInterface::class);
            $loader->existing(Argument::type('array'))->will(static fn (array $args): array => array_values(array_filter((array) $args[0], static fn (mixed $id): bool => in_array($id, $existing, true))));
            $loaders[$type] = $loader->reveal();
        }

        /** @var ObjectProphecy<ChannelRepositoryInterface<ChannelInterface>> $channels */
        $channels = $this->prophesize(ChannelRepositoryInterface::class);
        $channels->findBy(Argument::type('array'))->will(self::withCodes(Channel::class, ['WEB', 'MOBILE']));
        /** @var ObjectProphecy<RepositoryInterface<LocaleInterface>> $locales */
        $locales = $this->prophesize(RepositoryInterface::class);
        $locales->findBy(Argument::type('array'))->will(self::withCodes(Locale::class, ['en_US']));
        /** @var ObjectProphecy<RepositoryInterface<CustomerGroupInterface>> $customerGroups */
        $customerGroups = $this->prophesize(RepositoryInterface::class);
        $customerGroups->findBy(Argument::type('array'))->willReturn([]);
        /** @var ObjectProphecy<RepositoryInterface<ProductAttributeInterface>> $productAttributes */
        $productAttributes = $this->prophesize(RepositoryInterface::class);
        $productAttributes->findBy(Argument::type('array'))->willReturn([]);

        return new UnresolvedReferences(
            $referenceFinder,
            Conditions::matcher(),
            new ServiceLocator(array_map(static fn (ReferenceTypeLoaderInterface $loader): \Closure => static fn (): ReferenceTypeLoaderInterface => $loader, $loaders)),
            $channels->reveal(),
            $customerGroups->reveal(),
            $locales->reveal(),
            $productAttributes->reveal(),
        );
    }

    /**
     * A promise of the records with those of the codes asked for that the target has
     *
     * @param class-string<Channel|Locale> $class
     * @param list<string> $codes the codes the target has
     */
    private static function withCodes(string $class, array $codes): \Closure
    {
        return static function (array $args) use ($class, $codes): array {
            $criteria = $args[0];
            \assert(is_array($criteria) && is_array($criteria['code']));

            $records = [];
            foreach ($criteria['code'] as $code) {
                if (in_array($code, $codes, true)) {
                    $record = new $class();
                    $record->setCode($code);
                    $records[] = $record;
                }
            }

            return $records;
        };
    }

    /**
     * Puts the entry in the target, changed at the time given
     *
     * @param list<ExportedPlacement> $placements a snippet's placements there
     */
    private function inTarget(EntryInterface $entry, string $changedAt, bool $draft = false, bool $scheduled = false, array $placements = []): void
    {
        $entry->setChangedAt(new \DateTimeImmutable($changedAt));
        $this->targetEntries[$entry->getUuid()] = $entry;
        if ($draft) {
            $this->drafts[] = $entry->getUuid();
        }

        if ($scheduled) {
            $this->scheduledPublishes[] = $entry->getUuid();
        }

        if ($entry instanceof Snippet) {
            $this->placements[$entry->getUuid()] = array_map(static function (ExportedPlacement $exported) use ($entry): PlacementInterface {
                $placement = new Placement($entry, $exported->slot, 0);
                $placement->setUuid($exported->uuid);
                $placement->setPageScope($exported->scope);

                return $placement;
            }, $placements);
        }
    }

    /**
     * @param list<ReadEntry> $entries
     * @param list<ReadPreset> $presets
     * @param list<ReadMediaItem> $mediaItems
     * @param list<ExportedMediaFolder> $mediaFolders
     * @param list<ImportRefusal> $refusals
     */
    private static function export(array $entries, array $presets = [], array $mediaItems = [], array $mediaFolders = [], array $refusals = []): ReadExport
    {
        $entriesByUuid = $presetsByUuid = $mediaItemsByUuid = [];
        foreach ($entries as $entry) {
            $entriesByUuid[$entry->uuid()] = $entry;
        }

        foreach ($presets as $preset) {
            $presetsByUuid[$preset->uuid()] = $preset;
        }

        foreach ($mediaItems as $mediaItem) {
            $mediaItemsByUuid[$mediaItem->uuid()] = $mediaItem;
        }

        return new ReadExport(new Manifest('2.0.0', new \DateTimeImmutable('2026-11-13T15:00:00Z')), $entriesByUuid, $presetsByUuid, $mediaFolders, $mediaItemsByUuid, $refusals);
    }

    /**
     * @param list<ExportedPlacement> $placements
     */
    private static function entry(EntryInterface $content, ?EntrySettings $settings = null, array $placements = [], bool $picked = true): ReadEntry
    {
        return new ReadEntry(
            new ExportedEntry(new \DateTimeImmutable(self::EXPORTED), $settings ?? self::settings(), $placements, []),
            $content,
            self::contentTypes()->getByEntry($content),
            $picked,
        );
    }

    /**
     * The settings of an entry as it's created: enabled, always shown, in no channel, to everyone
     *
     * @param list<string> $channels
     * @param list<string> $homepageOf
     */
    private static function settings(array $channels = [], array $homepageOf = []): EntrySettings
    {
        return new EntrySettings(true, new DateWindow(), $channels, Audience::everyone(), $homepageOf);
    }

    /**
     * @param list<StoredBlock> $blocks
     */
    private static function page(string $uuid, string $title, ?string $slug = null, array $blocks = []): Page
    {
        $page = new Page();
        $page->setUuid($uuid);
        $page->setCurrentLocale('en_US');
        $page->setTitle($title);
        $page->setSlug($slug);
        $page->setBlockTree(new StoredTree(null, $blocks));

        return $page;
    }

    /**
     * @param list<StoredBlock> $blocks
     */
    private static function snippet(string $uuid, string $title, array $blocks = [], ?string $slug = null): Snippet
    {
        $snippet = new Snippet();
        $snippet->setUuid($uuid);
        $snippet->setCurrentLocale('en_US');
        $snippet->setTitle($title);
        $snippet->setSlug($slug);
        $snippet->setBlockTree(new StoredTree(null, $blocks));

        return $snippet;
    }

    /**
     * @param list<StoredBlock> $blocks
     */
    private static function preset(string $name, string $uuid = self::PRESET, bool $picked = true, array $blocks = []): ReadPreset
    {
        return new ReadPreset(new Preset($name, $blocks, uuid: $uuid, changedAt: new \DateTimeImmutable(self::EXPORTED)), $picked);
    }

    private static function targetPreset(string $uuid, string $name, string $changedAt): EditorPreset
    {
        $preset = new EditorPreset();
        $preset->setUuid($uuid);
        (new EditorPresetContent())->write($preset, new Preset($name, [], changedAt: new \DateTimeImmutable($changedAt)));

        return $preset;
    }

    /**
     * An image named Autumn banner, as the export holds it
     *
     * @param array<string, string> $alt
     */
    private static function mediaItem(string $uuid = self::BANNER, ?string $folder = null, bool $picked = true, array $alt = []): ReadMediaItem
    {
        return new ReadMediaItem(
            new ExportedMediaItem($uuid, $folder, MediaKind::Image, 'Autumn banner', 'autumn-banner.jpg', 'image/jpeg', 482113, 2400, 1000, null, $alt, null, null, self::CHECKSUM, new \DateTimeImmutable(self::EXPORTED)),
            new CheckedFile(MediaKind::Image, 'image/jpeg', 'jpg', '/nowhere/autumn-banner.jpg', 482113, 2400, 1000, self::CHECKSUM),
            $picked,
            '/nowhere/autumn-banner.jpg',
        );
    }

    /**
     * An image in the target with the export's file, named as given
     */
    private static function targetMediaItem(string $uuid, string $name, string $changedAt): MediaItem
    {
        $mediaItem = new MediaItem();
        $mediaItem->setUuid($uuid);
        $mediaItem->setName($name);
        $mediaItem->setOriginalFilename('autumn-banner.jpg');
        $mediaItem->setPath('media/autumn-banner.jpg');
        $mediaItem->setChecksum(self::CHECKSUM);
        $mediaItem->setChangedAt(new \DateTimeImmutable($changedAt));

        return $mediaItem;
    }

    private static function snippetBlock(string $snippet): StoredBlock
    {
        return new StoredBlock(\Symfony\Component\Uid\Uuid::v7()->toRfc4122(), 'snippet', 1, fields: ['snippet' => $snippet]);
    }

    /**
     * @param list<StoredBlock> $children
     */
    private static function teaser(?string $image = null, ?string $readMore = null, array $children = []): StoredBlock
    {
        return new StoredBlock(\Symfony\Component\Uid\Uuid::v7()->toRfc4122(), 'teaser', 1, fields: array_filter(['image' => $image, 'readMore' => $readMore], static fn (?string $value): bool => null !== $value), children: $children);
    }

    private static function embedCode(string $code): StoredBlock
    {
        return new StoredBlock(self::EMBED, 'embed_code', 1, fields: ['code' => $code]);
    }

    private static function contentTypes(): ContentTypeRegistry
    {
        return new ContentTypeRegistry([
            'page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page'],
            'snippet' => ['model' => Snippet::class, 'translation' => EntryTranslation::class, 'resource' => 'setono_sylius_cms.snippet'],
        ]);
    }

    /**
     * @param list<PlannedRecord> $records
     *
     * @return list<array{string, ImportAction}>
     */
    private static function actions(array $records): array
    {
        return array_map(static fn (PlannedRecord $record): array => [$record->uuid, $record->action], $records);
    }

    /**
     * @param list<ImportConflict> $conflicts
     *
     * @return list<array{string, string, string, array<array-key, mixed>}>
     */
    private static function conflicts(array $conflicts): array
    {
        return array_map(static function (ImportConflict $conflict): array {
            self::assertInstanceOf(TranslatableMessage::class, $conflict->message);

            return [$conflict->kind->value, $conflict->uuid, $conflict->message->getMessage(), $conflict->message->getParameters()];
        }, $conflicts);
    }

    /**
     * @param list<ImportRefusal> $refusals
     *
     * @return list<array{string|null, string, array<array-key, mixed>}>
     */
    private static function refusals(array $refusals): array
    {
        return array_map(static function (ImportRefusal $refusal): array {
            self::assertInstanceOf(TranslatableMessage::class, $refusal->reason);

            return [$refusal->uuid, $refusal->reason->getMessage(), $refusal->reason->getParameters()];
        }, $refusals);
    }
}
