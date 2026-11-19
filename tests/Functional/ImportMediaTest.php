<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional;

use Setono\SyliusCMSPlugin\Media\FocalPoint;
use Setono\SyliusCMSPlugin\Media\MediaKind;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\MediaItemRepositoryInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tests\Support\Imports;
use Setono\SyliusCMSPlugin\Tests\Support\MediaItems;
use Setono\SyliusCMSPlugin\Tests\Support\TemporaryDirectory;
use Setono\SyliusCMSPlugin\Transfer\Format\ExportFormat;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;

/**
 * An import brings media items with their folders, metadata and original files, and treats every file in the export
 * as untrusted input, checked like an upload. See "Media files" and "What an import does" in docs/spec/transfer.md
 */
final class ImportMediaTest extends KernelTestCase
{
    use Entries;
    use Imports;
    use MediaItems;
    use TemporaryDirectory;

    private const PAGE = '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e';

    /** An image in the folder Autumn, inside Campaigns */
    private const BANNER = '0193a5be-70aa-7c11-8e2d-1b2c3d4e5f60';

    /** A video in Unsorted, whose poster is POSTER */
    private const VIDEO = '0193a5be-70ab-7c12-8e2d-1b2c3d4e5f61';

    /** An image in Campaigns */
    private const POSTER = '0193a5be-70ac-7c13-8e2d-1b2c3d4e5f62';

    private const CAMPAIGNS = '0193a5be-7500-7e4f-9a5b-6c7d8e9f0a1b';

    private const AUTUMN = '0193a5be-7501-7f5a-8b6c-7d8e9f0a1b2c';

    /**
     * @test
     */
    public function it_refuses_a_zip_holding_a_path_outside_its_root_with_1_and_writes_nothing(): void
    {
        $tester = self::import(['path' => $this->zipOf(self::exportFixture(), ['../evil' => 'Escaped'])]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('The export holds ../evil, which isn\'t inside its folder', self::display($tester));
        self::assertStringContainsString('The import refused. Nothing changed.', self::display($tester));
        self::assertNull(self::entries()->findOneByUuid(self::PAGE));
        self::assertNull(self::mediaItem(self::BANNER));
        self::assertSame([], $this->newlyStored());
    }

    /**
     * @test
     */
    public function it_refuses_a_file_that_does_not_match_its_checksum_naming_the_media_item(): void
    {
        $export = $this->copyOfFixture();
        file_put_contents(sprintf('%s/media/%s/autumn-banner.png', $export, self::BANNER), self::png(48, 20, [10, 200, 10]));

        $tester = self::import(['path' => $export]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('The media item Autumn banner can\'t be imported: autumn-banner.png doesn\'t match its checksum, so it isn\'t the file that was exported.', self::display($tester));
        self::assertNull(self::entries()->findOneByUuid(self::PAGE));
        self::assertSame([], $this->newlyStored());
    }

    /**
     * @test
     */
    public function it_imports_media_items_with_their_folders_metadata_and_files_as_uploads_are_stored(): void
    {
        $tester = self::import(['path' => self::exportFixture()]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        $display = self::display($tester);
        self::assertStringContainsString('Autumn banner, came along: create', $display);
        self::assertStringContainsString('Campaigns, came along: create', $display);
        self::assertStringContainsString('Imported: 3 entries, 1 editor preset, 3 media items and 2 media folders created.', $display);

        $banner = self::mediaItem(self::BANNER);
        self::assertNotNull($banner);
        self::assertSame(MediaKind::Image, $banner->getKind());
        self::assertSame('Autumn banner', $banner->getName());
        self::assertSame('autumn-banner.png', $banner->getOriginalFilename());
        self::assertSame('image/png', $banner->getMimeType());
        self::assertSame([48, 20], [$banner->getWidth(), $banner->getHeight()]);
        self::assertSame(['en_US' => 'A woman in a red coat among autumn leaves'], $banner->getAltTexts());
        self::assertEquals(new FocalPoint(0.42, 0.35), $banner->getFocalPoint());
        self::assertSame('2026-11-11T12:00:00Z', ExportFormat::dateTime($banner->getChangedAt()));
        self::assertStringStartsWith(self::BANNER . '/', $banner->getPath());
        self::assertSame(hash_file('sha256', self::mediaDirectory() . '/' . $banner->getPath()), $banner->getChecksum());

        // The folders are created with their UUIDs, the banner's inside its parent
        $autumn = $banner->getFolder();
        self::assertNotNull($autumn);
        self::assertSame([self::AUTUMN, 'Autumn'], [$autumn->getUuid(), $autumn->getName()]);
        self::assertSame(self::CAMPAIGNS, $autumn->getParent()?->getUuid());

        $video = self::mediaItem(self::VIDEO);
        self::assertNotNull($video);
        self::assertSame(MediaKind::Video, $video->getKind());
        self::assertNull($video->getFolder());
        self::assertSame(self::POSTER, $video->getPoster()?->getUuid());
        self::assertSame('Photo: Studio North', $video->getPoster()->getCredit());

        self::assertCount(3, $this->newlyStored());
    }

    /**
     * @test
     */
    public function it_ignores_what_the_json_says_of_the_file_and_reads_it_from_the_file(): void
    {
        $export = $this->copyOfFixture();
        $path = sprintf('%s/media/%s.json', $export, self::BANNER);
        file_put_contents($path, str_replace(['"image/png"', '"size": 130', '"width": 48'], ['"image/jpeg"', '"size": 482113', '"width": 2400'], (string) file_get_contents($path)));

        $tester = self::import(['path' => $export]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        $banner = self::mediaItem(self::BANNER);
        self::assertNotNull($banner);
        self::assertSame('image/png', $banner->getMimeType());
        self::assertSame(48, $banner->getWidth());
        self::assertSame(filesize(self::mediaDirectory() . '/' . $banner->getPath()), $banner->getSize());
        self::assertStringEndsWith('.png', $banner->getPath());
    }

    /**
     * @test
     */
    public function it_leaves_a_media_item_that_came_along_as_the_target_has_it(): void
    {
        $logo = $this->uploadMediaItem('logo.png', self::png(10, 10));
        self::setMediaItemUuid($logo, self::BANNER);
        $before = self::mediaItem(self::BANNER);
        self::assertNotNull($before);

        $tester = self::import(['path' => self::exportFixture()]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('Autumn banner, came along: left alone', self::display($tester));
        // Nor is its folder created, as nothing the import writes is in it
        self::assertStringNotContainsString('Autumn, came along', self::display($tester));

        $after = self::mediaItem(self::BANNER);
        self::assertNotNull($after);
        self::assertSame([$before->getName(), $before->getPath(), $before->getChecksum(), null], [$after->getName(), $after->getPath(), $after->getChecksum(), $after->getFolder()]);
        self::assertCount(3, $this->newlyStored(), 'The logo the test uploaded, and the video and its poster the import stored');
    }

    /**
     * @test
     */
    public function it_creates_missing_folders_and_neither_moves_nor_renames_existing_ones(): void
    {
        $elsewhere = self::createMediaFolder('Elsewhere');
        $campaigns = self::createMediaFolder('Campaigns, renamed here', $elsewhere);
        self::entityManager()->getConnection()->update('setono_sylius_cms__media_folder', ['uuid' => self::CAMPAIGNS], ['id' => $campaigns->getId()]);

        $tester = self::import(['path' => self::exportFixture()]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('Campaigns, came along: left alone', self::display($tester));
        self::assertStringContainsString('Autumn, came along: create', self::display($tester));

        $autumn = self::mediaItem(self::BANNER)?->getFolder();
        self::assertNotNull($autumn);
        self::assertSame(self::AUTUMN, $autumn->getUuid());
        $parent = $autumn->getParent();
        self::assertNotNull($parent);
        self::assertSame([self::CAMPAIGNS, 'Campaigns, renamed here', 'Elsewhere'], [$parent->getUuid(), $parent->getName(), $parent->getParent()?->getName()]);
    }

    private static function entries(): EntryRepositoryInterface
    {
        self::entityManager()->clear();

        /** @var EntryRepositoryInterface $entries */
        $entries = self::getContainer()->get(EntryRepositoryInterface::class);

        return $entries;
    }

    private static function mediaItem(string $uuid): ?MediaItemInterface
    {
        self::entityManager()->clear();

        /** @var MediaItemRepositoryInterface $mediaItems */
        $mediaItems = self::getContainer()->get(MediaItemRepositoryInterface::class);

        return $mediaItems->findOneByUuid($uuid);
    }

    /**
     * Gives a media item of the target the UUID one in the committed export has, as when environments were cloned from
     * each other
     */
    private static function setMediaItemUuid(MediaItemInterface $mediaItem, string $uuid): void
    {
        self::entityManager()->getConnection()->update('setono_sylius_cms__media_item', ['uuid' => $uuid], ['id' => $mediaItem->getId()]);
        self::entityManager()->clear();
    }

    /**
     * @return list<string> what the test stored in the media storage: the folders under each media item's UUID
     */
    private function newlyStored(): array
    {
        return array_values(array_diff(self::storedMedia(), $this->storedBefore ?? []));
    }
}
