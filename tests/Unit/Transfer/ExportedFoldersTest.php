<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Transfer;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Model\MediaFolder;
use Setono\SyliusCMSPlugin\Model\MediaFolderInterface;
use Setono\SyliusCMSPlugin\Model\MediaItem;
use Setono\SyliusCMSPlugin\Transfer\ExportedFolders;

/**
 * The folders media/folders.json lists: those holding an exported media item, with their parents, parents first. See
 * "media/folders.json" in docs/spec/transfer.md
 */
final class ExportedFoldersTest extends TestCase
{
    /**
     * @test
     */
    public function it_lists_the_folders_holding_the_media_items_with_their_parents_first_and_siblings_by_name(): void
    {
        $campaigns = self::folder('Campaigns');
        $winter = self::folder('Winter', $campaigns);
        $autumn = self::folder('Autumn', $campaigns);
        $leaves = self::folder('Leaves', $autumn);
        $brand = self::folder('Brand');
        self::folder('Logos', $brand);

        $folders = ExportedFolders::of([self::in($leaves), self::in($winter), self::in(null), self::in($brand), self::in($leaves)]);

        self::assertSame(['Brand', 'Campaigns', 'Autumn', 'Leaves', 'Winter'], array_map(static fn (MediaFolderInterface $folder): string => $folder->getName(), $folders));
    }

    /**
     * @test
     */
    public function it_lists_no_folder_for_media_items_in_unsorted(): void
    {
        self::assertSame([], ExportedFolders::of([self::in(null)]));
    }

    private static function folder(string $name, ?MediaFolderInterface $parent = null): MediaFolder
    {
        $folder = new MediaFolder();
        $folder->setName($name);
        $folder->setParent($parent);

        return $folder;
    }

    private static function in(?MediaFolderInterface $folder): MediaItem
    {
        $mediaItem = new MediaItem();
        $mediaItem->setFolder($folder);

        return $mediaItem;
    }
}
