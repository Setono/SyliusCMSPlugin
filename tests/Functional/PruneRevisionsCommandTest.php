<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional;

use Setono\SyliusCMSPlugin\Model\RevisionInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tests\Support\Revisions;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * setono:cms:revisions:prune deletes the revisions beyond setono_sylius_cms.revisions.keep (50) per entry.
 * See "Revisions" in docs/spec/publishing.md and "Console commands" in docs/spec/extending.md
 */
final class PruneRevisionsCommandTest extends KernelTestCase
{
    use Entries;
    use Revisions;

    /**
     * @test
     */
    public function it_deletes_each_entrys_revisions_beyond_the_newest_50(): void
    {
        self::bootKernel();
        $about = self::createPage('About us', 'about-us', 'Hello', published: true);
        $contact = self::createPage('Contact', 'contact', 'Hello', published: true);
        // Stored out of order, so the oldest by publish time aren't the first stored
        foreach ([...range(3, 52), 1, 2] as $day) {
            self::storeRevision($about, [], sprintf('2026-01-01 10:00:00 +%d days', $day));
        }
        self::storeRevision($contact, [], '2026-01-01 10:00:00');

        $tester = new CommandTester((new Application(self::$kernel ?? self::bootKernel()))->find('setono:cms:revisions:prune'));
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Deleted 2 revisions. Every entry keeps its newest 50.', (string) preg_replace('/\s+/', ' ', $tester->getDisplay()));

        $kept = self::revisionsOf($about);
        self::assertCount(50, $kept);
        self::assertSame('2026-02-22', $kept[0]->getPublishedAt()->format('Y-m-d'));
        self::assertSame('2026-01-04', $kept[49]->getPublishedAt()->format('Y-m-d'));
        self::assertNotContains('2026-01-02', array_map(static fn (RevisionInterface $revision): string => $revision->getPublishedAt()->format('Y-m-d'), $kept));
        self::assertCount(1, self::revisionsOf($contact));

        // Nothing is left to prune
        $tester->execute([]);
        self::assertStringContainsString('Nothing to prune: no entry has more than 50 revisions.', (string) preg_replace('/\s+/', ' ', $tester->getDisplay()));
    }
}
