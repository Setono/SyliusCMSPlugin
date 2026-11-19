<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional;

use Setono\SyliusCMSPlugin\Json\CanonicalJson;
use Setono\SyliusCMSPlugin\Media\FocalPoint;
use Setono\SyliusCMSPlugin\Media\MediaKind;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\MediaFolderInterface;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;
use Setono\SyliusCMSPlugin\Model\PlacementInterface;
use Setono\SyliusCMSPlugin\Preset\EditorPresetSaverInterface;
use Setono\SyliusCMSPlugin\Preset\Preset;
use Setono\SyliusCMSPlugin\Tests\Support\CommandOutput;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tests\Support\MediaItems;
use Setono\SyliusCMSPlugin\Tests\Support\Placements;
use Setono\SyliusCMSPlugin\Tests\Support\TemporaryDirectory;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * setono:cms:export writes the picked entries, editor presets and media items, with the snippets and media items they
 * use, to a folder or a zip, in the export format: canonical JSON files and the media items' original files. See
 * "Export format" and "Commands" in docs/spec/transfer.md
 */
final class ExportCommandTest extends KernelTestCase
{
    use ClockSensitiveTrait;
    use CommandOutput;
    use Entries;
    use MediaItems;
    use Placements;
    use TemporaryDirectory;

    /** The committed export the command must write, byte for byte */
    private const FIXTURE = __DIR__ . '/../Fixture/export';

    private const PAGE = '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e';

    private const SNIPPET = '0193a5be-7255-7f00-8a1b-2c3d4e5f6a7b';

    private const INNER_SNIPPET = '0193a5be-7256-7a01-8b2c-3d4e5f6a7b8c';

    private const OTHER_SNIPPET = '0193a5be-7257-7b02-8c3d-4e5f6a7b8c9d';

    private const PRESET = '0193a5be-7400-7d3e-8f4a-5b6c7d8e9f0a';

    private const PLACEMENT = '0193a5be-7300-7b1c-8d2e-3f4a5b6c7d8e';

    private const PRECEDING_PLACEMENT = '0193a5be-7301-7c2d-9e3f-4a5b6c7d8e9f';

    private const CAMPAIGNS = '0193a5be-7500-7e4f-9a5b-6c7d8e9f0a1b';

    private const AUTUMN = '0193a5be-7501-7f5a-8b6c-7d8e9f0a1b2c';

    private const LOGOS = '0193a5be-7502-7a6b-9c7d-8e9f0a1b2c3d';

    private const IMAGE = '0193a5be-70aa-7c11-8e2d-1b2c3d4e5f60';

    private const VIDEO = '0193a5be-70ab-7c12-8e2d-1b2c3d4e5f61';

    private const POSTER = '0193a5be-70ac-7c13-8e2d-1b2c3d4e5f62';

    /**
     * @test
     */
    public function it_writes_a_picked_page_and_the_editor_presets_with_what_they_use_as_the_committed_export(): void
    {
        $this->createContent();
        self::mockTime('2026-11-13 15:00:00 UTC');
        $directory = $this->temporaryDirectory() . '/export';

        $tester = self::export(['dir' => $directory, '--entry' => [self::PAGE], '--presets' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('Exported 3 entries, 1 editor preset and 3 media items', self::display($tester));
        self::assertStringContainsString('2 snippets and 3 media items came along because the picked records use them.', self::display($tester));
        self::assertSameFiles(self::FIXTURE, $directory);
    }

    /**
     * @test
     */
    public function it_writes_the_same_folder_into_a_zip_at_its_root(): void
    {
        $this->createContent();
        self::mockTime('2026-11-13 15:00:00 UTC');
        $directory = $this->temporaryDirectory() . '/export';

        $tester = self::export(['dir' => $directory, '--entry' => [self::PAGE], '--presets' => true, '--zip' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertDirectoryDoesNotExist($directory);
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($directory . '.zip'));
        $unzipped = $this->temporaryDirectory() . '/unzipped';
        self::assertTrue($zip->extractTo($unzipped));
        $zip->close();
        self::assertSameFiles(self::FIXTURE, $unzipped);
    }

    /**
     * @test
     */
    public function it_exports_an_entrys_published_content_and_never_its_draft(): void
    {
        $page = self::createPage('Winter sale', 'winter-sale', 'Up to 50% off', published: true);
        self::saveChanges($page, [['op' => 'set_entry_value', 'field' => 'title', 'locale' => 'en_US', 'value' => 'Winter clearance']]);
        self::assertNotNull(self::draftOf($page));
        $directory = $this->temporaryDirectory() . '/export';

        $tester = self::export(['dir' => $directory, '--entry' => [$page->getUuid()]]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        $file = (string) file_get_contents(sprintf('%s/entries/%s.json', $directory, $page->getUuid()));
        /** @var array{entry: array{translations: array{en_US: array{fields: array{title: string}}}}} $exported */
        $exported = CanonicalJson::decode($file);
        self::assertSame('Winter sale', $exported['entry']['translations']['en_US']['fields']['title']);
        self::assertStringNotContainsString('Winter clearance', $file);
    }

    /**
     * @test
     */
    public function it_exports_a_never_published_entry_as_its_rows_hold_it(): void
    {
        $page = self::createPage('Spring sale', 'spring-sale', 'Fresh arrivals');
        $directory = $this->temporaryDirectory() . '/export';

        $tester = self::export(['dir' => $directory, '--content-type' => ['page']]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        /** @var array{entry: array{translations: array{en_US: array{fields: array{title: string}}}}} $exported */
        $exported = CanonicalJson::decode((string) file_get_contents(sprintf('%s/entries/%s.json', $directory, $page->getUuid())));
        self::assertSame('Spring sale', $exported['entry']['translations']['en_US']['fields']['title']);
    }

    /**
     * @test
     */
    public function it_needs_at_least_one_option_and_writes_nothing_without_one(): void
    {
        $directory = $this->temporaryDirectory() . '/export';

        $tester = self::export(['dir' => $directory]);

        self::assertSame(Command::INVALID, $tester->getStatusCode());
        self::assertStringContainsString('Pick what to export with --entry, --content-type, --media-folder, --presets or --all.', self::display($tester));
        self::assertDirectoryDoesNotExist($directory);
    }

    /**
     * @test
     */
    public function it_refuses_a_directory_that_is_not_empty_a_zip_that_exists_and_a_record_there_is_none_of(): void
    {
        $directory = $this->temporaryDirectory();
        $this->temporaryFile('notes.txt', 'Mine');

        $notEmpty = self::export(['dir' => $directory, '--presets' => true]);
        self::assertSame(Command::INVALID, $notEmpty->getStatusCode());
        // SymfonyStyle wraps a long path where it likes
        self::assertStringContainsString(sprintf('%smustbeanemptydirectoryornotexistyet.', $directory), self::squashed($notEmpty));
        self::assertSame(['notes.txt'], array_values(array_diff((array) scandir($directory), ['.', '..'])));

        $this->temporaryFile('notes.zip', 'Mine');
        $zipExists = self::export(['dir' => $directory . '/notes', '--presets' => true, '--zip' => true]);
        self::assertSame(Command::INVALID, $zipExists->getStatusCode());
        self::assertStringContainsString(sprintf('%s/notes.zipexistsalready.', $directory), self::squashed($zipExists));
        self::assertSame('Mine', file_get_contents($directory . '/notes.zip'));

        $unknown = self::export(['dir' => $directory . '/export', '--entry' => ['0193a5be-dead-7000-8000-000000000000']]);
        self::assertSame(Command::INVALID, $unknown->getStatusCode());
        self::assertStringContainsString('There is no entry with the UUID 0193a5be-dead-7000-8000-000000000000.', self::display($unknown));
        self::assertDirectoryDoesNotExist($directory . '/export');
    }

    /**
     * A page with a snippet, which holds an image, a snippet of its own and a video with a poster, and is placed in a
     * slot after another snippet's placement, and an editor preset with the image. Every UUID and time is fixed, so the
     * export is the committed one
     */
    private function createContent(): void
    {
        self::bootKernel();

        $campaigns = self::createMediaFolder('Campaigns');
        $autumn = self::createMediaFolder('Autumn', $campaigns);
        $logos = self::createMediaFolder('Logos');
        self::fix('setono_sylius_cms__media_folder', $campaigns, self::CAMPAIGNS);
        self::fix('setono_sylius_cms__media_folder', $autumn, self::AUTUMN);
        self::fix('setono_sylius_cms__media_folder', $logos, self::LOGOS);

        $image = $this->uploadMediaItem('autumn-banner.png', self::fixtureFile(self::IMAGE, 'autumn-banner.png'), $autumn);
        $image->setName('Autumn banner');
        $image->setAltText('en_US', 'A woman in a red coat among autumn leaves');
        $image->setFocalPoint(new FocalPoint(0.42, 0.35));
        $poster = $this->uploadMediaItem('lookbook-poster.png', self::fixtureFile(self::POSTER, 'lookbook-poster.png'), $campaigns);
        $poster->setCredit('Photo: Studio North');
        $video = $this->uploadMediaItem('lookbook.mp4', self::fixtureFile(self::VIDEO, 'lookbook.mp4'));
        self::assertSame(MediaKind::Video, $video->getKind());
        $video->setPoster($poster);
        // A media item no export here uses, in a folder of its own
        $this->uploadMediaItem('logo.png', self::png(), $logos);
        self::entityManager()->flush();
        self::fix('setono_sylius_cms__media_item', $image, self::IMAGE, '2026-11-11 12:00:00');
        self::fix('setono_sylius_cms__media_item', $poster, self::POSTER, '2026-11-11 12:05:00');
        self::fix('setono_sylius_cms__media_item', $video, self::VIDEO, '2026-11-11 12:10:00');

        $inner = self::createSnippet('Newsletter signup', [self::heading('0193a5be-0000-7000-8000-000000000001', 'Join our newsletter')], published: true);
        self::fix('setono_sylius_cms__entry', $inner, self::INNER_SNIPPET);

        $snippet = self::createSnippet('Autumn banner', [
            new StoredBlock('0193a5be-0000-7000-8000-000000000002', 'image', 1, fields: ['image' => self::IMAGE, 'caption' => ['en_US' => 'The autumn collection']]),
            new StoredBlock('0193a5be-0000-7000-8000-000000000003', 'video', 1, fields: ['video' => self::VIDEO]),
            new StoredBlock('0193a5be-0000-7000-8000-000000000004', 'snippet', 1, fields: ['snippet' => self::INNER_SNIPPET]),
        ], published: true);
        self::fix('setono_sylius_cms__entry', $snippet, self::SNIPPET);

        $other = self::createSnippet('Free returns', published: true);
        self::fix('setono_sylius_cms__entry', $other, self::OTHER_SNIPPET);
        self::fix('setono_sylius_cms__placement', self::place($other, 'product.after_price'), self::PRECEDING_PLACEMENT);
        $placement = self::place($snippet, 'product.after_price');
        self::scopeUnchecked($placement, [self::productInTaxon(['t_shirts'], includeChildren: true)]);
        self::fix('setono_sylius_cms__placement', $placement, self::PLACEMENT);

        $page = self::createPageWithBlocks('Autumn sale', 'autumn-sale', [
            self::heading('0193a5be-0000-7000-8000-000000000005', 'Autumn sale'),
            new StoredBlock('0193a5be-0000-7000-8000-000000000006', 'snippet', 1, fields: ['snippet' => self::SNIPPET]),
        ], published: true, channels: ['WEB']);
        self::fix('setono_sylius_cms__entry', $page, self::PAGE);

        /** @var EditorPresetSaverInterface $presets */
        $presets = self::getContainer()->get(EditorPresetSaverInterface::class);
        $presets->save(new Preset('Autumn hero', [
            new StoredBlock('0193a5be-0000-7000-8000-000000000007', 'image', 1, fields: ['image' => self::IMAGE]),
        ], 'Campaigns', uuid: self::PRESET), new \DateTimeImmutable('2026-11-10T08:00:00Z'));

        // Last, since saving a placement bumps its snippet's changedAt
        foreach ([self::INNER_SNIPPET => '2026-11-09 07:00:00', self::SNIPPET => '2026-11-12 09:30:00', self::OTHER_SNIPPET => '2026-11-12 09:45:00', self::PAGE => '2026-11-12 10:15:00'] as $uuid => $changedAt) {
            self::entityManager()->getConnection()->executeStatement(
                'UPDATE setono_sylius_cms__entry SET created_at = :createdAt, changed_at = :changedAt WHERE uuid = :uuid',
                ['createdAt' => '2026-11-01 08:30:00', 'changedAt' => $changedAt, 'uuid' => $uuid],
            );
        }

        self::entityManager()->clear();
    }

    /**
     * Gives the record a fixed UUID, and a media item fixed times, in the database
     */
    private static function fix(string $table, EntryInterface|MediaItemInterface|MediaFolderInterface|PlacementInterface $record, string $uuid, ?string $changedAt = null): void
    {
        $values = ['uuid' => $uuid];
        if (null !== $changedAt) {
            $values += ['created_at' => '2026-11-01 08:00:00', 'changed_at' => $changedAt];
        }

        self::entityManager()->getConnection()->update($table, $values, ['id' => $record->getId()]);
    }

    private static function fixtureFile(string $uuid, string $filename): string
    {
        return (string) file_get_contents(sprintf('%s/media/%s/%s', self::FIXTURE, $uuid, $filename));
    }

    /**
     * That the directories hold the same files, byte for byte
     */
    private static function assertSameFiles(string $expected, string $actual): void
    {
        $files = self::filesIn($expected);
        self::assertSame($files, self::filesIn($actual));
        foreach ($files as $file) {
            self::assertSame(file_get_contents($expected . '/' . $file), file_get_contents($actual . '/' . $file), $file);
        }
    }

    /**
     * @return list<string> the paths of the files in the directory, relative to it, sorted
     */
    private static function filesIn(string $directory): array
    {
        $files = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)) as $file) {
            \assert($file instanceof \SplFileInfo);
            $files[] = substr($file->getPathname(), strlen($directory) + 1);
        }

        sort($files, \SORT_STRING);

        return $files;
    }

    /**
     * @param array<string, mixed> $input
     */
    private static function export(array $input): CommandTester
    {
        self::bootKernel();
        $tester = new CommandTester((new Application(self::$kernel))->find('setono:cms:export'));
        $tester->execute($input, ['decorated' => false]);

        return $tester;
    }
}
