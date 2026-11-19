<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Transfer\Import;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Media\CheckedFile;
use Setono\SyliusCMSPlugin\Media\MediaKind;
use Setono\SyliusCMSPlugin\Transfer\Format\ExportedMediaFolder;
use Setono\SyliusCMSPlugin\Transfer\Format\ExportedMediaItem;
use Setono\SyliusCMSPlugin\Transfer\Format\Manifest;
use Setono\SyliusCMSPlugin\Transfer\Import\ReadExport;
use Setono\SyliusCMSPlugin\Transfer\Import\ReadMediaItem;

/**
 * Media items import with their folders: the folders the media items an import writes sit in, with their parents. See
 * "What a transfer moves" in docs/spec/transfer.md
 */
final class ReadExportTest extends TestCase
{
    private const CAMPAIGNS = '0193a5be-7500-7e4f-9a5b-6c7d8e9f0a1b';

    private const AUTUMN = '0193a5be-7501-7f5a-8b6c-7d8e9f0a1b2c';

    private const SPRING = '0193a5be-7502-7a6b-8c7d-8e9f0a1b2c3d';

    private const ARCHIVE = '0193a5be-7503-7b7c-8d8e-9f0a1b2c3d4e';

    /**
     * @test
     */
    public function it_finds_the_folders_of_media_items_with_their_parents_parents_first(): void
    {
        $export = new ReadExport(
            new Manifest('2.0.0', new \DateTimeImmutable()),
            [],
            [],
            [
                new ExportedMediaFolder(self::ARCHIVE, 'Archive', null),
                new ExportedMediaFolder(self::CAMPAIGNS, 'Campaigns', null),
                new ExportedMediaFolder(self::AUTUMN, 'Autumn', self::CAMPAIGNS),
                new ExportedMediaFolder(self::SPRING, 'Spring', self::CAMPAIGNS),
            ],
            [
                'a' => self::mediaItem('a', self::AUTUMN),
                'b' => self::mediaItem('b', self::ARCHIVE),
                'c' => self::mediaItem('c', null),
                'd' => self::mediaItem('d', self::AUTUMN),
            ],
        );

        self::assertSame([self::CAMPAIGNS, self::AUTUMN], self::uuids($export->foldersOf(['a', 'c', 'd'])));
        self::assertSame([self::ARCHIVE, self::CAMPAIGNS, self::AUTUMN], self::uuids($export->foldersOf(['d', 'b'])));
        self::assertSame([], $export->foldersOf(['c']));
        self::assertSame([], $export->foldersOf([]));
    }

    /**
     * @param list<ExportedMediaFolder> $folders
     *
     * @return list<string>
     */
    private static function uuids(array $folders): array
    {
        return array_map(static fn (ExportedMediaFolder $folder): string => $folder->uuid, $folders);
    }

    private static function mediaItem(string $uuid, ?string $folder): ReadMediaItem
    {
        return new ReadMediaItem(
            new ExportedMediaItem($uuid, $folder, MediaKind::Document, $uuid, $uuid . '.pdf', 'application/pdf', 1, null, null, null, [], null, null, str_repeat('a', 64), new \DateTimeImmutable()),
            new CheckedFile(MediaKind::Document, 'application/pdf', 'pdf', '/tmp/' . $uuid, 1, null, null, str_repeat('a', 64)),
            false,
            '/tmp/' . $uuid,
        );
    }
}
