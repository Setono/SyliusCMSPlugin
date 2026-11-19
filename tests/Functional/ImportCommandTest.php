<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional;

use Setono\SyliusCMSPlugin\Event\BeforeEntryPublish;
use Setono\SyliusCMSPlugin\Event\EntryCreated;
use Setono\SyliusCMSPlugin\Event\EntryPublished;
use Setono\SyliusCMSPlugin\Event\EntrySettingsChanged;
use Setono\SyliusCMSPlugin\Event\ImportFinished;
use Setono\SyliusCMSPlugin\Event\PlacementsChanged;
use Setono\SyliusCMSPlugin\Json\CanonicalJson;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Model\PlacementInterface;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Preset\EditorPresetSaverInterface;
use Setono\SyliusCMSPlugin\Preset\Preset;
use Setono\SyliusCMSPlugin\Preset\PresetProviderInterface;
use Setono\SyliusCMSPlugin\Publishing\PublishSchedulerInterface;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tests\Support\Imports;
use Setono\SyliusCMSPlugin\Tests\Support\MediaItems;
use Setono\SyliusCMSPlugin\Tests\Support\Placements;
use Setono\SyliusCMSPlugin\Tests\Support\Revisions;
use Setono\SyliusCMSPlugin\Tests\Support\ScheduledPublishes;
use Setono\SyliusCMSPlugin\Tests\Support\TemporaryDirectory;
use Setono\SyliusCMSPlugin\Transfer\Format\ExportFormat;
use Setono\SyliusCMSPlugin\Transfer\Import\UnresolvedReference;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * setono:cms:import reads an export, plans what it creates, updates, leaves unchanged or alone, and applies the plan all
 * or nothing: picked entries land in their drafts, or their rows when they were never published here, with their
 * on-save settings and placements, which apply at once. See "What an import does", "Conflicts" and "Commands" in
 * docs/spec/transfer.md
 */
final class ImportCommandTest extends KernelTestCase
{
    use Entries;
    use Imports;
    use MediaItems;
    use Placements;
    use Revisions;
    use ScheduledPublishes;
    use TemporaryDirectory;

    /** The committed export: a picked page with a snippet in it, which holds another, an editor preset, and media */
    private const FIXTURE = __DIR__ . '/../Fixture/export';

    private const PAGE = '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e';

    private const SNIPPET = '0193a5be-7255-7f00-8a1b-2c3d4e5f6a7b';

    private const INNER_SNIPPET = '0193a5be-7256-7a01-8b2c-3d4e5f6a7b8c';

    private const PRESET = '0193a5be-7400-7d3e-8f4a-5b6c7d8e9f0a';

    private const PLACEMENT = '0193a5be-7300-7b1c-8d2e-3f4a5b6c7d8e';

    /** The banner, the video and its poster */
    private const MEDIA_ITEMS = ['0193a5be-70aa-7c11-8e2d-1b2c3d4e5f60', '0193a5be-70ab-7c12-8e2d-1b2c3d4e5f61', '0193a5be-70ac-7c13-8e2d-1b2c3d4e5f62'];

    /** Campaigns, and Autumn inside it */
    private const MEDIA_FOLDERS = ['0193a5be-7500-7e4f-9a5b-6c7d8e9f0a1b', '0193a5be-7501-7f5a-8b6c-7d8e9f0a1b2c'];

    /** The placement before the snippet's in the source slot */
    private const PRECEDING_PLACEMENT = '0193a5be-7301-7c2d-9e3f-4a5b6c7d8e9f';

    /**
     * @test
     */
    public function it_imports_an_export_into_an_empty_database_as_rows_with_their_settings_and_placements(): void
    {
        $tester = self::import(['path' => self::FIXTURE]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('Imported: 3 entries, 1 editor preset, 3 media items and 2 media folders created.', self::display($tester));

        // Never published here, so the content is in the rows, and nothing is published
        $page = self::entry(self::PAGE);
        self::assertInstanceOf(PageInterface::class, $page);
        self::assertFalse($page->isPublished());
        self::assertNull(self::draftOf($page));
        self::assertSame('Autumn sale', $page->getTranslation('en_US')->getTitle());
        self::assertSame('autumn-sale', $page->getTranslation('en_US')->getSlug());
        self::assertSame(['0193a5be-0000-7000-8000-000000000005', '0193a5be-0000-7000-8000-000000000006'], $page->getBlockTree()->keys());
        self::assertSame(['WEB'], $page->getChannelCodes());
        self::assertSame('2026-11-01T08:30:00Z', ExportFormat::dateTime($page->getCreatedAt()));

        // The snippets came along, created as the target lacked them, with the snippet's placement in its slot
        $snippet = self::entry(self::SNIPPET);
        self::assertInstanceOf(SnippetInterface::class, $snippet);
        self::assertSame('Autumn banner', $snippet->getTranslation('en_US')->getTitle());
        self::assertSame(['0193a5be-0000-7000-8000-000000000002', '0193a5be-0000-7000-8000-000000000003', '0193a5be-0000-7000-8000-000000000004'], $snippet->getBlockTree()->keys());
        $placements = self::placements()->findBySnippet($snippet);
        self::assertCount(1, $placements);
        self::assertSame(self::PLACEMENT, $placements[0]->getUuid());
        self::assertSame('product.after_price', $placements[0]->getSlot());
        self::assertEquals([['kind' => 'product_in_taxon', 'negate' => false, 'params' => ['includeChildren' => true, 'taxons' => ['t_shirts']]]], $placements[0]->getPageScope());
        self::assertSame('Newsletter signup', self::entry(self::INNER_SNIPPET)->getTranslation('en_US')->getTitle());

        /** @var PresetProviderInterface $presets */
        $presets = self::getContainer()->get(PresetProviderInterface::class);
        $preset = $presets->find(self::PRESET);
        self::assertNotNull($preset);
        self::assertSame('Autumn hero', $preset->name);
        self::assertSame('2026-11-10T08:00:00Z', null === $preset->changedAt ? null : ExportFormat::dateTime($preset->changedAt));
    }

    /**
     * @test
     */
    public function it_sets_changed_at_to_the_exports_value_on_every_write(): void
    {
        self::import(['path' => self::FIXTURE]);

        // Saving the settings and the placements would otherwise have bumped it to now
        self::assertSame('2026-11-12T10:15:00Z', ExportFormat::dateTime(self::entry(self::PAGE)->getChangedAt()));
        self::assertSame('2026-11-12T09:30:00Z', ExportFormat::dateTime(self::entry(self::SNIPPET)->getChangedAt()));
        self::assertSame('2026-11-09T07:00:00Z', ExportFormat::dateTime(self::entry(self::INNER_SNIPPET)->getChangedAt()));
    }

    /**
     * @test
     */
    public function it_changes_nothing_on_a_dry_run_and_exits_as_the_real_run_would(): void
    {
        $tester = self::import(['path' => self::FIXTURE, '--dry-run' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('Autumn sale (page): create', self::display($tester));
        self::assertStringContainsString('Autumn banner (snippet), came along: create', self::display($tester));
        self::assertStringContainsString('The dry run would import: 3 entries, 1 editor preset, 3 media items and 2 media folders created.', self::display($tester));
        self::assertNull(self::entries()->findOneByUuid(self::PAGE));
        self::assertNull(self::entries()->findOneByUuid(self::SNIPPET));

        /** @var PresetProviderInterface $presets */
        $presets = self::getContainer()->get(PresetProviderInterface::class);
        self::assertNull($presets->find(self::PRESET));

        // A refused import's dry run exits as it would
        $page = self::createPage('Autumn sale', 'autumn-sale', published: true);
        self::assertSame(1, self::import(['path' => self::FIXTURE, '--dry-run' => true])->getStatusCode());
        self::assertSame(1, self::import(['path' => self::FIXTURE])->getStatusCode());
        self::assertNull(self::entries()->findOneByUuid(self::PAGE));
        self::assertNotNull(self::entries()->findOneByUuid($page->getUuid()));
    }

    /**
     * @test
     */
    public function it_refuses_a_picked_entry_with_a_draft_here_with_3_and_overwrites_it_with_overwrite(): void
    {
        $page = self::createPageWithUuid(self::PAGE, 'Autumn sale (old)', 'autumn-sale');
        self::saveChanges($page, [['op' => 'set_entry_value', 'field' => 'title', 'locale' => 'en_US', 'value' => 'Autumn sale (work in progress)']]);

        $refused = self::import(['path' => self::FIXTURE]);

        self::assertSame(3, $refused->getStatusCode(), $refused->getDisplay());
        self::assertStringContainsString('Autumn sale (page): update', self::display($refused));
        self::assertStringContainsString('Autumn sale has unpublished changes here.', self::display($refused));
        self::assertStringContainsString('Run it with --overwrite', self::display($refused));
        self::assertSame(3, self::import(['path' => self::FIXTURE, '--dry-run' => true])->getStatusCode());
        $page = self::entry(self::PAGE);
        self::assertInstanceOf(PageInterface::class, $page);
        self::assertStringContainsString('work in progress', (string) self::draftOf($page)?->getContent());

        $overwritten = self::import(['path' => self::FIXTURE, '--overwrite' => true]);

        self::assertSame(Command::SUCCESS, $overwritten->getStatusCode(), $overwritten->getDisplay());
        self::assertStringContainsString('Overwritten ----------- * Autumn sale has unpublished changes here.', self::display($overwritten));
        self::assertStringContainsString('Imported: 2 entries, 1 editor preset, 3 media items and 2 media folders created, 1 entry updated.', self::display($overwritten));
        $page = self::entry(self::PAGE);
        self::assertInstanceOf(PageInterface::class, $page);
        // The export's content is the draft, and what shoppers see stays until it's published
        self::assertSame('Autumn sale (old)', $page->getTranslation('en_US')->getTitle());
        $draft = (string) self::draftOf($page)?->getContent();
        self::assertStringContainsString('"title":"Autumn sale"', $draft);
        self::assertStringNotContainsString('work in progress', $draft);
        self::assertSame('2026-11-12T10:15:00Z', ExportFormat::dateTime($page->getChangedAt()));
    }

    /**
     * @test
     */
    public function it_refuses_a_url_another_entry_has_here_with_1_even_with_overwrite(): void
    {
        self::createPage('Autumn sale here', 'autumn-sale', published: true, channels: ['WEB']);

        $tester = self::import(['path' => self::FIXTURE, '--overwrite' => true]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('Autumn sale would have the URL /en_US/autumn-sale, which Autumn sale here has in WEB.', self::display($tester));
        self::assertNull(self::entries()->findOneByUuid(self::PAGE));
    }

    /**
     * @test
     */
    public function it_refuses_a_block_type_the_code_here_does_not_have_with_1(): void
    {
        $export = $this->copyOfFixture();
        $file = sprintf('%s/entries/%s.json', $export, self::INNER_SNIPPET);
        file_put_contents($file, str_replace('"type": "heading"', '"type": "countdown"', (string) file_get_contents($file)));

        $tester = self::import(['path' => $export]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('Newsletter signup has blocks of the type "countdown", which the code here doesn\'t have. Deploy the code first.', self::display($tester));
        self::assertNull(self::entries()->findOneByUuid(self::PAGE));
    }

    /**
     * @test
     */
    public function it_exits_with_2_for_a_path_that_is_not_an_export_it_reads(): void
    {
        $missing = self::import(['path' => $this->temporaryDirectory() . '/missing']);
        self::assertSame(Command::INVALID, $missing->getStatusCode());
        self::assertStringContainsString('isneitheranexportfoldernorazip', self::squashed($missing));

        $empty = self::import(['path' => $this->temporaryDirectory()]);
        self::assertSame(Command::INVALID, $empty->getStatusCode());
        self::assertStringContainsString('hasnomanifest.json', self::squashed($empty));

        $notAZip = self::import(['path' => $this->temporaryFile('export.zip', 'Not a zip')]);
        self::assertSame(Command::INVALID, $notAZip->getStatusCode());

        $export = $this->copyOfFixture();
        file_put_contents($export . '/manifest.json', str_replace('"format": 1', '"format": 2', (string) file_get_contents($export . '/manifest.json')));
        $newerFormat = self::import(['path' => $export]);
        self::assertSame(Command::INVALID, $newerFormat->getStatusCode());
        self::assertStringContainsString('The export has the format 2, but this version of the plugin reads format 1', self::display($newerFormat));
    }

    /**
     * @test
     */
    public function it_imports_the_same_export_from_a_zip(): void
    {
        $zip = new \ZipArchive();
        $file = $this->temporaryDirectory() . '/export.zip';
        self::assertTrue($zip->open($file, \ZipArchive::CREATE));
        foreach (self::filesIn(self::FIXTURE) as $path) {
            $zip->addFile(self::FIXTURE . '/' . $path, $path);
        }
        $zip->close();

        $tester = self::import(['path' => $file]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('Imported: 3 entries, 1 editor preset, 3 media items and 2 media folders created.', self::display($tester));
    }

    /**
     * @test
     */
    public function it_publishes_every_entry_it_writes_with_publish_as_any_publish_would(): void
    {
        $tester = self::import(['path' => self::FIXTURE, '--publish' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        foreach ([self::PAGE => '2026-11-12T10:15:00Z', self::SNIPPET => '2026-11-12T09:30:00Z', self::INNER_SNIPPET => '2026-11-09T07:00:00Z'] as $uuid => $changedAt) {
            $entry = self::entry($uuid);
            self::assertTrue($entry->isPublished(), $uuid);
            self::assertCount(1, self::revisionsOf($entry), $uuid);
            // The publish keeps the export's changedAt too
            self::assertSame($changedAt, ExportFormat::dateTime(self::entry($uuid)->getChangedAt()), $uuid);
        }

        self::bootKernel();
        $client = self::getContainer()->get('test.client');
        self::assertInstanceOf(KernelBrowser::class, $client);
        $client->request('GET', '/en_US/autumn-sale');
        self::assertSame(200, $client->getResponse()->getStatusCode());
        self::assertStringContainsString('Join our newsletter', (string) $client->getResponse()->getContent());
    }

    /**
     * @test
     */
    public function it_fails_the_whole_import_when_a_publish_fails(): void
    {
        self::bootKernel();
        self::eventDispatcher()->addListener(BeforeEntryPublish::class, static function (BeforeEntryPublish $event): void {
            if ('Autumn banner' === $event->entry->getTranslation('en_US')->getTitle()) {
                $event->veto('The campaign was postponed');
            }
        });

        $tester = self::import(['path' => self::FIXTURE, '--publish' => true], boot: false);

        self::assertSame(Command::FAILURE, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('Autumn banner can\'t be published: The campaign was postponed', self::display($tester));
        self::assertStringContainsString('The import refused. Nothing changed.', self::display($tester));
        foreach ([self::PAGE, self::SNIPPET, self::INNER_SNIPPET] as $uuid) {
            self::assertNull(self::entries()->findOneByUuid($uuid), $uuid);
        }

        /** @var PresetProviderInterface $presets */
        $presets = self::getContainer()->get(PresetProviderInterface::class);
        self::assertNull($presets->find(self::PRESET));
    }

    /**
     * @test
     */
    public function it_refuses_an_entry_publish_validation_fails_for_with_publish_and_lists_each(): void
    {
        // The headings' text is required
        $export = $this->copyOfFixture();
        foreach ([self::PAGE => 'Autumn sale', self::INNER_SNIPPET => 'Join our newsletter'] as $uuid => $heading) {
            $file = sprintf('%s/entries/%s.json', $export, $uuid);
            file_put_contents($file, str_replace(sprintf('"en_US": "%s"', $heading), '"en_US": ""', (string) file_get_contents($file)));
        }

        $dryRun = self::import(['path' => $export, '--publish' => true, '--dry-run' => true]);
        $tester = self::import(['path' => $export, '--publish' => true]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode(), $tester->getDisplay());
        self::assertSame(Command::FAILURE, $dryRun->getStatusCode(), $dryRun->getDisplay());
        self::assertStringContainsString('Newsletter signup can\'t be published:', self::display($tester));
        self::assertStringContainsString('Autumn sale can\'t be published:', self::display($tester));
        self::assertNull(self::entries()->findOneByUuid(self::PAGE));

        // Without --publish nothing is validated, so it imports as it is
        self::assertSame(Command::SUCCESS, self::import(['path' => $export])->getStatusCode());
    }

    /**
     * @test
     */
    public function it_dispatches_import_finished_with_the_result_after_the_events_of_what_it_wrote_once_it_commits(): void
    {
        self::bootKernel();
        $connection = self::entityManager()->getConnection();
        // Connected, so the level counts the transaction every test runs in
        $connection->executeQuery('SELECT 1');
        $level = $connection->getTransactionNestingLevel();
        /** @var \ArrayObject<int, object> $heard the events the listeners heard, in order */
        $heard = new \ArrayObject();
        foreach ([EntryCreated::class, EntrySettingsChanged::class, PlacementsChanged::class, EntryPublished::class, ImportFinished::class] as $event) {
            self::eventDispatcher()->addListener($event, static function (object $event) use ($heard, $connection, $level): void {
                // Only once the import's transaction committed
                self::assertSame($level, $connection->getTransactionNestingLevel());
                $heard->append($event);
            });
        }

        self::import(['path' => self::FIXTURE, '--dry-run' => true, '--publish' => true], boot: false);
        self::assertCount(0, $heard);

        self::import(['path' => self::FIXTURE, '--publish' => true], boot: false);

        $events = $heard->getArrayCopy();
        $finished = array_pop($events);
        self::assertInstanceOf(ImportFinished::class, $finished);
        self::assertSame([self::INNER_SNIPPET, self::SNIPPET, self::PAGE, self::PRESET, ...self::MEDIA_ITEMS, ...self::MEDIA_FOLDERS], $finished->result->created());
        self::assertSame([], $finished->result->updated());
        self::assertSame([self::INNER_SNIPPET, self::SNIPPET, self::PAGE, self::PRESET, ...self::MEDIA_ITEMS, ...self::MEDIA_FOLDERS], $finished->getAffectedUuids());
        self::assertEquals([new UnresolvedReference(self::SNIPPET, 'Autumn banner', 'taxon', 't_shirts')], $finished->result->unresolvedReferences());
        self::assertCount(3, array_filter($events, static fn (object $event): bool => $event instanceof EntryCreated));
        self::assertCount(3, array_filter($events, static fn (object $event): bool => $event instanceof EntryPublished));
        self::assertCount(1, array_filter($events, static fn (object $event): bool => $event instanceof PlacementsChanged));

        // An import that changes nothing touched nothing
        $heard->exchangeArray([]);
        self::import(['path' => self::FIXTURE], boot: false);
        $events = $heard->getArrayCopy();
        $finished = array_pop($events);
        self::assertInstanceOf(ImportFinished::class, $finished);
        self::assertSame([], $finished->getAffectedUuids());
        self::assertSame([self::INNER_SNIPPET, self::SNIPPET, self::PAGE, self::PRESET, ...self::MEDIA_ITEMS], $finished->result->unchanged());
        self::assertSame([], $events);
    }

    /**
     * @test
     */
    public function it_skips_records_identical_to_the_targets_even_when_the_target_changed_them_later(): void
    {
        self::import(['path' => self::FIXTURE, '--publish' => true]);
        // Saving the same settings again here bumps changedAt, which would make the page stale, but it's identical
        self::entityManager()->getConnection()->update('setono_sylius_cms__entry', ['changed_at' => '2026-11-18 12:00:00'], ['uuid' => self::PAGE]);

        $tester = self::import(['path' => self::FIXTURE]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('Imported: 3 entries, 1 editor preset and 3 media items unchanged.', self::display($tester));
        $page = self::entry(self::PAGE);
        self::assertInstanceOf(PageInterface::class, $page);
        self::assertNull(self::draftOf($page));
        self::assertCount(1, self::revisionsOf($page));
    }

    /**
     * @test
     */
    public function it_leaves_a_snippet_that_came_along_as_the_target_has_it(): void
    {
        $snippet = self::createSnippet('Autumn banner, edited here', published: true);
        $placement = self::place($snippet, 'shop.footer');
        self::entityManager()->getConnection()->update('setono_sylius_cms__entry', ['uuid' => self::SNIPPET], ['id' => $snippet->getId()]);

        $tester = self::import(['path' => self::FIXTURE]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('Autumn banner (snippet), came along: left alone', self::display($tester));
        self::assertStringContainsString('Imported: 2 entries, 1 editor preset, 3 media items and 2 media folders created, 1 entry left alone.', self::display($tester));
        $snippet = self::entry(self::SNIPPET);
        self::assertInstanceOf(SnippetInterface::class, $snippet);
        self::assertSame('Autumn banner, edited here', $snippet->getTranslation('en_US')->getTitle());
        self::assertSame([$placement->getUuid()], array_map(static fn (PlacementInterface $placement): string => $placement->getUuid(), self::placements()->findBySnippet($snippet)));
    }

    /**
     * @test
     */
    public function it_refuses_a_picked_entry_with_a_scheduled_publish_here_and_cancels_it_with_overwrite(): void
    {
        $page = self::createPageWithUuid(self::PAGE, 'Autumn sale (old)', 'autumn-sale');
        /** @var PublishSchedulerInterface $scheduler */
        $scheduler = self::getContainer()->get(PublishSchedulerInterface::class);
        $scheduler->schedule($page, new \DateTimeImmutable('+1 week'));

        $refused = self::import(['path' => self::FIXTURE]);

        self::assertSame(3, $refused->getStatusCode(), $refused->getDisplay());
        self::assertStringContainsString('Autumn sale has a scheduled publish here.', self::display($refused));

        $overwritten = self::import(['path' => self::FIXTURE, '--overwrite' => true]);

        self::assertSame(Command::SUCCESS, $overwritten->getStatusCode(), $overwritten->getDisplay());
        self::assertNull(self::scheduledPublishOf(self::entry(self::PAGE)));
    }

    /**
     * @test
     */
    public function it_refuses_a_picked_editor_preset_changed_here_after_the_exports_copy_until_overwrite(): void
    {
        self::bootKernel();
        /** @var EditorPresetSaverInterface $saver */
        $saver = self::getContainer()->get(EditorPresetSaverInterface::class);
        $saver->save(new Preset('Autumn hero, renamed here', [], uuid: self::PRESET), new \DateTimeImmutable('2026-11-15T09:00:00Z'));

        $refused = self::import(['path' => self::FIXTURE]);

        self::assertSame(3, $refused->getStatusCode(), $refused->getDisplay());
        self::assertStringContainsString('Autumn hero changed here at 2026-11-15T09:00:00Z, after the export\'s copy from 2026-11-10T08:00:00Z.', self::display($refused));

        $overwritten = self::import(['path' => self::FIXTURE, '--overwrite' => true]);

        self::assertSame(Command::SUCCESS, $overwritten->getStatusCode(), $overwritten->getDisplay());
        /** @var PresetProviderInterface $presets */
        $presets = self::getContainer()->get(PresetProviderInterface::class);
        $preset = $presets->find(self::PRESET);
        self::assertSame('Autumn hero', $preset?->name);
        self::assertSame('2026-11-10T08:00:00Z', null === $preset->changedAt ? null : ExportFormat::dateTime($preset->changedAt));
    }

    /**
     * @test
     */
    public function it_places_an_imported_placement_right_after_the_nearest_of_its_preceding_placements_here(): void
    {
        $other = self::place(self::createSnippet('Size guide', published: true), 'product.after_price');
        $preceding = self::place(self::createSnippet('Free returns', published: true), 'product.after_price');
        self::entityManager()->getConnection()->update('setono_sylius_cms__placement', ['uuid' => self::PRECEDING_PLACEMENT], ['id' => $preceding->getId()]);
        $last = self::place(self::createSnippet('Gift wrapping', published: true), 'product.after_price');

        $tester = self::import(['path' => self::FIXTURE]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::entityManager()->clear();
        $slot = self::placements()->findBySlots(['product.after_price']);
        usort($slot, static fn (PlacementInterface $a, PlacementInterface $b): int => $a->getPosition() <=> $b->getPosition());
        self::assertSame(
            [$other->getUuid(), self::PRECEDING_PLACEMENT, self::PLACEMENT, $last->getUuid()],
            array_map(static fn (PlacementInterface $placement): string => $placement->getUuid(), $slot),
        );
        self::assertSame([0, 1, 2, 3], array_map(static fn (PlacementInterface $placement): int => $placement->getPosition(), $slot));
    }

    /**
     * @test
     */
    public function it_lists_the_homepage_changes_and_makes_them(): void
    {
        $home = self::createPage('Home', 'home', published: true, channels: ['WEB']);
        self::setHomepageOf($home, 'WEB');
        $export = $this->copyOfFixture();
        $file = sprintf('%s/entries/%s.json', $export, self::PAGE);
        file_put_contents($file, str_replace('"homepageOf": []', '"homepageOf": ["WEB"]', (string) file_get_contents($file)));

        $tester = self::import(['path' => $export]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('Homepage changes ---------------- * WEB: Autumn sale becomes its homepage, instead of Home', self::display($tester));
        $page = self::entry(self::PAGE);
        self::assertInstanceOf(PageInterface::class, $page);
        self::assertSame(['WEB'], $page->getHomepageChannelCodes());
        $home = self::entry($home->getUuid());
        self::assertInstanceOf(PageInterface::class, $home);
        self::assertSame([], $home->getHomepageChannelCodes());
    }

    /**
     * The export's page isn't the homepage of the channel its copy here is, which changed here after the export
     *
     * @test
     */
    public function it_lists_a_page_that_stops_being_a_homepage_and_takes_it_off(): void
    {
        $page = self::createPageWithUuid(self::PAGE, 'Autumn sale (old)', 'autumn-sale');
        self::setHomepageOf($page, 'WEB');

        $tester = self::import(['path' => self::FIXTURE, '--overwrite' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('Homepage changes ---------------- * WEB: Autumn sale stops being its homepage', self::display($tester));
        $page = self::entry(self::PAGE);
        self::assertInstanceOf(PageInterface::class, $page);
        self::assertSame([], $page->getHomepageChannelCodes());
    }

    /**
     * @test
     */
    public function it_lists_the_references_that_do_not_resolve_here_and_imports_them_unchanged(): void
    {
        $export = $this->copyOfFixture();
        $path = sprintf('%s/entries/%s.json', $export, self::PAGE);
        /** @var array{settings: array<string, mixed>, entry: array{tree: array{blocks: list<array<string, mixed>>}}} $file */
        $file = CanonicalJson::decode((string) file_get_contents($path));
        $file['settings']['channels'] = ['MOBILE', 'WEB'];
        $file['settings']['audience'] = ['mode' => 'only', 'groups' => ['@guest', 'vip']];
        $file['entry']['tree']['blocks'][0]['fields'] = ['text' => ['en_US' => ['$bind' => 'product.attribute', '$params' => ['attribute' => 'material'], '$fallback' => 'Autumn sale']]];
        $file['entry']['tree']['blocks'][] = ['key' => '0193a5be-0000-7000-8000-0000000000f1', 'type' => 'snippet', 'version' => 1, 'style' => null, 'fields' => ['snippet' => '0193a5be-dead-7000-8000-000000000000'], 'translated' => new \stdClass(), 'conditions' => [], 'children' => []];
        file_put_contents($path, CanonicalJson::encodeFile($file));

        $tester = self::import(['path' => $export]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        $display = self::display($tester);
        foreach (['Autumn sale: entry "0193a5be-dead-7000-8000-000000000000"', 'Autumn sale: channel "MOBILE"', 'Autumn sale: customer group "vip"', 'Autumn sale: product attribute "material"', 'Autumn banner: taxon "t_shirts"'] as $unresolved) {
            self::assertStringContainsString($unresolved, $display);
        }

        // What the export holds and the target has resolves
        foreach (['"WEB"', '"@guest"', '"en_US"', '0193a5be-70aa-7c11-8e2d-1b2c3d4e5f60', self::SNIPPET] as $resolved) {
            self::assertStringNotContainsString($resolved, $display);
        }

        self::assertSame(['MOBILE', 'WEB'], self::entry(self::PAGE)->getChannelCodes());
    }

    private static function entries(): EntryRepositoryInterface
    {
        self::entityManager()->clear();

        /** @var EntryRepositoryInterface $entries */
        $entries = self::getContainer()->get(EntryRepositoryInterface::class);

        return $entries;
    }

    private static function entry(string $uuid): EntryInterface
    {
        self::entityManager()->clear();

        /** @var EntryRepositoryInterface $entries */
        $entries = self::getContainer()->get(EntryRepositoryInterface::class);
        $entry = $entries->findOneByUuid($uuid);
        self::assertNotNull($entry, sprintf('There is no entry %s', $uuid));

        return $entry;
    }

    private static function eventDispatcher(): EventDispatcherInterface
    {
        /** @var EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = self::getContainer()->get('event_dispatcher');

        return $eventDispatcher;
    }
}
