<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Preset\PresetProviderInterface;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tests\Support\Placements;
use Setono\SyliusCMSPlugin\Tests\Support\TemporaryDirectory;
use Setono\SyliusCMSPlugin\Transfer\Format\ExportFormat;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * setono:cms:import reads an export, plans what it creates, updates, leaves unchanged or alone, and applies the plan all
 * or nothing: picked entries land in their drafts, or their rows when they were never published here, with their
 * on-save settings and placements, which apply at once. See "What an import does", "Conflicts" and "Commands" in
 * docs/spec/transfer.md
 */
final class ImportCommandTest extends KernelTestCase
{
    use Entries;
    use Placements;
    use TemporaryDirectory;

    /** The committed export: a picked page with a snippet in it, which holds another, and an editor preset */
    private const FIXTURE = __DIR__ . '/../Fixture/export';

    private const PAGE = '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e';

    private const SNIPPET = '0193a5be-7255-7f00-8a1b-2c3d4e5f6a7b';

    private const INNER_SNIPPET = '0193a5be-7256-7a01-8b2c-3d4e5f6a7b8c';

    private const PRESET = '0193a5be-7400-7d3e-8f4a-5b6c7d8e9f0a';

    private const PLACEMENT = '0193a5be-7300-7b1c-8d2e-3f4a5b6c7d8e';

    /**
     * @test
     */
    public function it_imports_an_export_into_an_empty_database_as_rows_with_their_settings_and_placements(): void
    {
        $tester = self::import(['path' => self::FIXTURE]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('Imported: 3 entries and 1 editor preset created.', self::display($tester));

        // Never published here, so the content is in the rows, and nothing is published
        $page = self::entry(self::PAGE);
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

    /**
     * @param array<string, mixed> $input
     */
    private static function import(array $input): CommandTester
    {
        self::bootKernel();
        $tester = new CommandTester((new Application(self::$kernel))->find('setono:cms:import'));
        $tester->execute($input, ['decorated' => false]);

        return $tester;
    }

    /**
     * The command's output on one line, as SymfonyStyle wraps it
     */
    private static function display(CommandTester $tester): string
    {
        return (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
    }
}
