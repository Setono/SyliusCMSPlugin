<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional;

use Setono\SyliusCMSPlugin\Model\MediaItemInterface;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\MediaItemRepositoryInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tests\Support\Imports;
use Setono\SyliusCMSPlugin\Tests\Support\MediaItems;
use Setono\SyliusCMSPlugin\Tests\Support\TemporaryDirectory;
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

    private const BANNER = '0193a5be-70aa-7c11-8e2d-1b2c3d4e5f60';

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
     * @return list<string> what the test stored in the media storage: the folders under each media item's UUID
     */
    private function newlyStored(): array
    {
        return array_values(array_diff(self::storedMedia(), $this->storedBefore ?? []));
    }
}
