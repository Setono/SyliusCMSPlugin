<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\MediaUsageInterface;
use Setono\SyliusCMSPlugin\Publishing\AutosaveInterface;
use Setono\SyliusCMSPlugin\Publishing\EntryDeleterInterface;
use Setono\SyliusCMSPlugin\Repository\MediaUsageRepositoryInterface;
use Setono\SyliusCMSPlugin\Tests\Functional\Fixture\MediaFieldsKernel;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The media usage index follows every write of an entry's content: a draft save, a discard, a publish and a delete. See
 * "Usage index" in docs/spec/media.md
 */
final class MediaUsageIndexTest extends KernelTestCase
{
    use Entries;

    private const BLOCK = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e70';

    private const A = '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0a';

    private const B = '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0b';

    private const C = '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0c';

    protected static function getKernelClass(): string
    {
        return MediaFieldsKernel::class;
    }

    /**
     * @test
     */
    public function it_indexes_what_a_new_entry_uses_as_in_its_draft(): void
    {
        $page = self::createPageWithBlocks('Gallery', 'gallery', [self::block(self::A, [self::B])]);

        self::assertSame([[self::A, true, false], [self::B, true, false]], self::indexOf($page));
    }

    /**
     * @test
     */
    public function it_indexes_a_draft_save_of_a_published_entry_apart_from_its_published_content(): void
    {
        $page = self::createPageWithBlocks('Gallery', 'gallery', [self::block(self::A, [self::B])], published: true);
        self::assertSame([[self::A, true, true], [self::B, true, true]], self::indexOf($page));

        self::saveChanges($page, [['op' => 'set_value', 'key' => self::BLOCK, 'field' => 'image', 'locale' => null, 'value' => self::C]]);

        self::assertSame([[self::A, false, true], [self::B, true, true], [self::C, true, false]], self::indexOf($page));
    }

    /**
     * @test
     */
    public function it_indexes_a_publish_and_a_discard(): void
    {
        $page = self::createPageWithBlocks('Gallery', 'gallery', [self::block(self::A, [])], published: true);

        self::saveChanges($page, [['op' => 'set_value', 'key' => self::BLOCK, 'field' => 'image', 'locale' => null, 'value' => self::B]]);
        self::publishEntry($page);

        self::assertSame([[self::B, true, true]], self::indexOf($page));

        self::saveChanges($page, [['op' => 'set_value', 'key' => self::BLOCK, 'field' => 'gallery', 'locale' => null, 'value' => [self::C]]]);
        self::assertSame([[self::B, true, true], [self::C, true, false]], self::indexOf($page));

        /** @var AutosaveInterface $autosave */
        $autosave = self::getContainer()->get(AutosaveInterface::class);
        $autosave->discard($page);

        self::assertSame([[self::B, true, true]], self::indexOf($page));
    }

    /**
     * @test
     */
    public function it_removes_the_rows_of_an_entry_that_is_deleted(): void
    {
        $page = self::createPageWithBlocks('Gallery', 'gallery', [self::block(self::A, [self::B])], published: true);
        $other = self::createPageWithBlocks('Other', 'other', [self::block(self::A, [])]);

        /** @var EntryDeleterInterface $deleter */
        $deleter = self::getContainer()->get(EntryDeleterInterface::class);
        $deleter->delete($page);

        /** @var MediaUsageRepositoryInterface $usages */
        $usages = self::getContainer()->get(MediaUsageRepositoryInterface::class);
        self::assertSame([self::A => 1], $usages->countByMediaItems([self::A, self::B]));
        self::assertSame([[self::A, true, false]], self::indexOf($other));
    }

    /**
     * @param list<string> $gallery
     */
    private static function block(?string $image, array $gallery): StoredBlock
    {
        return new StoredBlock(self::BLOCK, 'media_names', 1, fields: ['image' => $image, 'gallery' => $gallery]);
    }

    /**
     * The entry's rows of the index as the database has them: [the media item's UUID, in the draft, in the published
     * content], in the order of the UUIDs
     *
     * @return list<array{string, bool, bool}>
     */
    private static function indexOf(EntryInterface $entry): array
    {
        /** @var EntityManagerInterface $manager */
        $manager = self::getContainer()->get('setono_sylius_cms.manager.media_usage');

        /** @var list<array{uuid: string, inDraft: bool|int|string, inPublished: bool|int|string}> $rows */
        $rows = $manager->createQuery(sprintf('SELECT o.mediaItemUuid AS uuid, o.inDraft, o.inPublished FROM %s o WHERE o.entry = :entry ORDER BY o.mediaItemUuid', MediaUsageInterface::class))
            ->setParameter('entry', $entry->getId())
            ->getScalarResult()
        ;

        return array_map(static fn (array $row): array => [$row['uuid'], (bool) $row['inDraft'], (bool) $row['inPublished']], $rows);
    }
}
