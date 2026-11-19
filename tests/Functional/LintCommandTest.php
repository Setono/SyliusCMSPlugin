<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyManagerInterface;
use Setono\SyliusCMSPlugin\Tests\Functional\Fixture\DisabledLayoutKernel;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredLayout;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * setono:cms:lint reports stored values that don't fit their field type, blocks of unknown types and broken references,
 * in the published content and in drafts, and exits non-zero on any, for CI. See "Console commands" in
 * docs/spec/extending.md
 */
final class LintCommandTest extends KernelTestCase
{
    use Entries;

    private const HEADING = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f';

    private const PROMO = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e50';

    /**
     * @test
     */
    public function it_exits_zero_on_clean_data(): void
    {
        self::bootKernel();
        self::createPage('About us', 'about-us', 'Hello', published: true);
        self::createPageWithBlocks('Contact', 'contact', [self::heading(self::HEADING, 'Write to us')]);

        $tester = self::lint();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('[OK] No problems found.', self::display($tester));
    }

    /**
     * @test
     */
    public function it_exits_non_zero_listing_the_entry_and_the_block_of_a_value_that_does_not_fit_and_of_a_block_of_an_unknown_type(): void
    {
        self::bootKernel();
        $heading = self::heading(self::HEADING, 'Hello');
        $heading->fields['level'] = 'h9';
        $page = self::createPageWithBlocks('About us', 'about-us', [$heading, new StoredBlock(self::PROMO, 'promo', 1)]);
        $clean = self::createPage('Clean', 'clean', 'Hello');

        $tester = self::lint();

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        $lines = self::lines($tester);
        self::assertContains(sprintf('%s draft %s level Level in the Heading block holds a value that doesn\'t fit its field type, so it counts as empty.', $page->getUuid(), self::HEADING), $lines);
        self::assertContains(sprintf('%s draft %s A block of the type "promo", which the code no longer has, isn\'t shown.', $page->getUuid(), self::PROMO), $lines);
        self::assertStringNotContainsString($clean->getUuid(), self::display($tester));
        self::assertStringContainsString('[ERROR] Found 2 problems in 1 entry.', self::display($tester));
    }

    /**
     * @test
     */
    public function it_exits_non_zero_listing_a_reference_to_a_record_that_was_deleted(): void
    {
        self::bootKernel();
        $image = new StoredBlock(self::PROMO, 'image', 1, fields: ['image' => '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e51']);
        $page = self::createPageWithBlocks('About us', 'about-us', [$image]);

        $tester = self::lint();

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertContains(sprintf('%s draft %s image Image in the Image block refers to a media item that was deleted, so it isn\'t shown.', $page->getUuid(), self::PROMO), self::lines($tester));
    }

    /**
     * @test
     */
    public function it_lints_a_published_entrys_published_content_and_its_draft_apart(): void
    {
        self::bootKernel();
        $page = self::createPageWithBlocks('About us', 'about-us', [new StoredBlock(self::PROMO, 'promo', 1)], published: true);
        /** @var WorkingCopyManagerInterface $workingCopies */
        $workingCopies = self::getContainer()->get(WorkingCopyManagerInterface::class);
        $workingCopy = $workingCopies->getWorkingCopy($page);
        $heading = new StoredBlock(self::HEADING, 'heading', 1, fields: ['text' => ['en_US' => 'Hello', 'da_DK' => 42]]);
        $workingCopy->setBlockTree(new StoredTree($workingCopy->getBlockTree()->layout, [$heading]));
        $workingCopies->save($page, $workingCopy);
        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('setono_sylius_cms.manager.page');
        $manager->clear();

        $tester = self::lint();

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        $lines = self::lines($tester);
        self::assertContains(sprintf('%s published %s A block of the type "promo", which the code no longer has, isn\'t shown.', $page->getUuid(), self::PROMO), $lines);
        self::assertContains(sprintf('%s draft %s text da_DK Text in the Heading block holds a value that doesn\'t fit its field type, so it counts as empty.', $page->getUuid(), self::HEADING), $lines);
        self::assertStringContainsString('[ERROR] Found 2 problems in 1 entry.', self::display($tester));
    }

    /**
     * A project turns a layout off on purpose, and its pages render in the default layout, so it's a warning in the
     * admin only, and lint, which reports what's wrong with the stored content, leaves it out
     *
     * @test
     */
    public function it_leaves_out_an_entry_whose_layout_is_disabled(): void
    {
        static::$class = DisabledLayoutKernel::class;
        self::bootKernel();
        $page = self::createPage('About us', 'about-us', 'Hello', published: true);
        $page->setBlockTree(new StoredTree(new StoredLayout('landing', 1, []), $page->getBlockTree()->blocks));
        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('setono_sylius_cms.manager.page');
        $manager->flush();

        $tester = self::lint();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), self::display($tester));
    }

    /**
     * @test
     */
    public function it_reports_content_it_cant_read_and_lints_the_entries_after_it(): void
    {
        self::bootKernel();
        $broken = self::createPage('Broken', 'broken', 'Hello');
        $after = self::createPageWithBlocks('After', 'after', [new StoredBlock(self::PROMO, 'promo', 1)]);
        /** @var EntityManagerInterface $manager */
        $manager = self::getContainer()->get('setono_sylius_cms.manager.page');
        $manager->getConnection()->executeStatement('UPDATE setono_sylius_cms__entry SET block_tree = :tree WHERE id = :id', ['tree' => '{"blocks":42}', 'id' => $broken->getId()]);
        $manager->clear();

        $tester = self::lint();

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        $display = self::display($tester);
        self::assertStringContainsString(sprintf('%s draft This content can\'t be read:', $broken->getUuid()), $display);
        self::assertStringContainsString(sprintf('%s draft %s A block of the type "promo"', $after->getUuid(), self::PROMO), $display);
        self::assertStringContainsString('[ERROR] Found 2 problems in 2 entries.', $display);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        static::$class = null;
    }

    private static function lint(): CommandTester
    {
        $tester = new CommandTester((new Application(self::$kernel))->find('setono:cms:lint'));
        $tester->execute([], ['decorated' => false]);

        return $tester;
    }

    /**
     * The command's output on one line, as SymfonyStyle wraps it
     */
    private static function display(CommandTester $tester): string
    {
        return (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
    }

    /**
     * The rows of the table the command prints, each on one line with single spaces
     *
     * @return list<string>
     */
    private static function lines(CommandTester $tester): array
    {
        return array_values(array_filter(array_map(
            static fn (string $line): string => trim((string) preg_replace('/\s+/', ' ', $line)),
            explode("\n", $tester->getDisplay()),
        ), static fn (string $line): bool => '' !== $line));
    }
}
