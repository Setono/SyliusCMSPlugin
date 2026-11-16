<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional;

use Doctrine\Persistence\ObjectManager;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyManagerInterface;
use Setono\SyliusCMSPlugin\Tests\Functional\Fixture\UpgradesKernel;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tests\Support\Revisions;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * setono:cms:upgrade runs the upgraders over all stored data and writes it back. See "Console commands" in
 * docs/spec/extending.md. The test app's Callout block type is at version 2, where its field "text" became "message"
 */
final class UpgradeCommandTest extends KernelTestCase
{
    use Entries;
    use Revisions;

    private const CALLOUT = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e60';

    protected static function getKernelClass(): string
    {
        return UpgradesKernel::class;
    }

    /**
     * @test
     */
    public function it_reports_what_a_dry_run_would_upgrade_and_changes_nothing(): void
    {
        self::bootKernel();
        [$rowsPage, $draftPage, $currentPage] = self::pagesToUpgrade();
        $rows = self::reloadPage($rowsPage)->getBlockTreeJson();
        $draft = self::draftOf($draftPage)?->getContent();

        $tester = self::upgrade(['--dry-run' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        $display = self::display($tester);
        self::assertStringContainsString(sprintf('Would upgrade the block tree of the page %s', $rowsPage->getUuid()), $display);
        self::assertStringContainsString(sprintf('Would upgrade the draft of the page %s', $draftPage->getUuid()), $display);
        self::assertStringNotContainsString($currentPage->getUuid(), $display);
        self::assertStringContainsString('Dry run: 1 block tree and 1 draft would be upgraded. Nothing was written.', $display);

        self::assertSame($rows, self::reloadPage($rowsPage)->getBlockTreeJson());
        self::assertSame($draft, self::draftOf($draftPage)?->getContent());
    }

    /**
     * @test
     */
    public function it_rewrites_the_rows_and_drafts_that_need_upgrading_and_reports_them(): void
    {
        self::bootKernel();
        [$rowsPage, $draftPage, $currentPage] = self::pagesToUpgrade();
        $currentRows = self::reloadPage($currentPage)->getBlockTreeJson();
        $draftPageRows = self::reloadPage($draftPage)->getBlockTreeJson();

        $tester = self::upgrade();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        $display = self::display($tester);
        self::assertStringContainsString(sprintf('Upgraded the block tree of the page %s', $rowsPage->getUuid()), $display);
        self::assertStringContainsString(sprintf('Upgraded the draft of the page %s', $draftPage->getUuid()), $display);
        self::assertStringNotContainsString($currentPage->getUuid(), $display);
        self::assertStringContainsString('Upgraded 1 block tree and 1 draft.', $display);

        self::assertSame(
            sprintf('{"layout":{"code":"standard","version":1,"fields":{},"translated":{}},"blocks":[{"key":"%s","type":"callout","version":2,"style":null,"fields":{"message":{"en_US":"In the rows"}},"translated":{},"conditions":[],"children":[]}]}', self::CALLOUT),
            self::reloadPage($rowsPage)->getBlockTreeJson(),
        );
        self::assertStringContainsString(
            sprintf('"tree":{"layout":{"code":"standard","version":1,"fields":{},"translated":{}},"blocks":[{"key":"%s","type":"callout","version":2,"style":null,"fields":{"message":{"en_US":"In the draft"}},"translated":{},"conditions":[],"children":[]}]}}', self::CALLOUT),
            (string) self::draftOf($draftPage)?->getContent(),
        );
        self::assertSame($draftPageRows, self::reloadPage($draftPage)->getBlockTreeJson(), 'A published page\'s rows that need no upgrade are left');
        self::assertSame($currentRows, self::reloadPage($currentPage)->getBlockTreeJson());

        // Everything is up to date now
        $display = self::display(self::upgrade());
        self::assertStringContainsString('Everything is up to date.', $display);
        self::assertStringNotContainsString('Upgraded the', $display);
    }

    /**
     * @test
     */
    public function it_rewrites_the_revisions_that_need_upgrading(): void
    {
        self::bootKernel();
        $page = self::createPage('About us', 'about-us', 'Hello', published: true);
        self::storeRevision($page, [new StoredBlock(self::CALLOUT, 'callout', 1, fields: ['text' => ['en_US' => 'Published with version 1']])], '2026-11-01 10:00:00');
        $current = self::storeRevision($page, [new StoredBlock(self::CALLOUT, 'callout', 2, fields: ['message' => ['en_US' => 'Published with version 2']])], '2026-11-02 10:00:00')->getContent();

        $display = self::display(self::upgrade());

        self::assertStringContainsString(sprintf('Upgraded 1 revision of the page %s', $page->getUuid()), $display);
        self::assertStringContainsString('Upgraded 1 revision.', $display);
        [$newest, $oldest] = self::revisionsOf($page);
        self::assertSame($current, $newest->getContent());
        self::assertStringContainsString(
            sprintf('"tree":{"layout":{"code":"standard","version":1,"fields":{},"translated":{}},"blocks":[{"key":"%s","type":"callout","version":2,"style":null,"fields":{"message":{"en_US":"Published with version 1"}},"translated":{},"conditions":[],"children":[]}]}}', self::CALLOUT),
            $oldest->getContent(),
        );
    }

    /**
     * @test
     */
    public function it_warns_about_a_block_saved_with_a_newer_version_than_the_codes_and_keeps_it_as_it_is(): void
    {
        self::bootKernel();
        $page = self::createPageWithBlocks('About us', 'about-us', [new StoredBlock(self::CALLOUT, 'callout', 3, fields: ['body' => ['en_US' => 'From the future']])]);
        $rows = self::reloadPage($page)->getBlockTreeJson();

        $display = self::display(self::upgrade());

        self::assertStringContainsString(sprintf('In the page %s: The block type "callout" was saved with version 3, but the code has version 2. It\'s kept as it is', $page->getUuid()), $display);
        self::assertStringContainsString('Everything is up to date.', $display);
        self::assertSame($rows, self::reloadPage($page)->getBlockTreeJson());
    }

    /**
     * A never-published page with a Callout at version 1 in its rows, a published page with one in its draft,
     * and a published page that is up to date
     *
     * @return array{PageInterface, PageInterface, PageInterface}
     */
    private static function pagesToUpgrade(): array
    {
        $rowsPage = self::createPageWithBlocks('About us', 'about-us', [self::calloutAtVersion1('In the rows')]);

        $draftPage = self::createPage('Contact', 'contact', 'Hello', published: true);
        /** @var WorkingCopyManagerInterface $workingCopies */
        $workingCopies = self::getContainer()->get(WorkingCopyManagerInterface::class);
        $workingCopy = $workingCopies->getWorkingCopy($draftPage);
        $workingCopy->setBlockTree(new StoredTree($workingCopy->getBlockTree()->layout, [self::calloutAtVersion1('In the draft')]));
        $workingCopies->save($draftPage, $workingCopy);

        $currentPage = self::createPage('Current', 'current', 'Hello', published: true);

        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('setono_sylius_cms.manager.page');
        $manager->clear();

        return [$rowsPage, $draftPage, $currentPage];
    }

    /**
     * @param array<string, mixed> $input
     */
    private static function upgrade(array $input = []): CommandTester
    {
        $tester = new CommandTester((new Application(self::$kernel))->find('setono:cms:upgrade'));
        $tester->execute($input);

        return $tester;
    }

    /**
     * The command's output on one line, as SymfonyStyle wraps it
     */
    private static function display(CommandTester $tester): string
    {
        return (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
    }

    private static function calloutAtVersion1(string $text): StoredBlock
    {
        return new StoredBlock(self::CALLOUT, 'callout', 1, fields: ['text' => ['en_US' => $text]]);
    }
}
