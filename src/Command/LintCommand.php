<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Command;

use Doctrine\Persistence\ObjectManager;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Problem\ProblemKindInterface;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyManagerInterface;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Upgrade\StoredDataUpgraderInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Reports the problems of the kinds tagged for it in the stored content: stored values that don't fit their field type
 * and blocks of unknown types, and broken references once #245 adds them. It checks every entry's published content and
 * its draft apart, each upgraded as it's read, and exits non-zero on any problem, for CI and deploys. Revisions are
 * history that nobody edits, so they're left out: one restored brings its content into the draft, which is checked.
 * Editor presets (#270) join with their ticket. See "Console commands" in docs/spec/extending.md
 */
#[AsCommand(name: 'setono:cms:lint', description: 'Reports stored CMS values that don\'t fit their field type and blocks of unknown types')]
final class LintCommand extends Command
{
    private const BATCH_SIZE = 100;

    /**
     * @param ObjectManager $manager the manager of entries and drafts
     * @param iterable<ProblemKindInterface> $kinds the kinds it reports: those whose setono_sylius_cms.problem_kind tag has `lint: true`
     */
    public function __construct(
        private readonly EntryRepositoryInterface $entries,
        private readonly WorkingCopyManagerInterface $workingCopies,
        private readonly StoredDataUpgraderInterface $upgrader,
        private readonly TranslatorInterface $translator,
        private readonly ObjectManager $manager,
        private readonly iterable $kinds,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $rows = [];
        $entries = $batched = 0;
        foreach ($this->entries->iterateAll() as $entry) {
            $found = count($rows);
            foreach ($this->contents($entry) as $content => $checked) {
                foreach ($this->kinds as $kind) {
                    foreach ($kind->findIn($checked) as $problem) {
                        $rows[] = [$entry->getUuid(), $content, $problem->block, $problem->field, $problem->locale, $problem->message->trans($this->translator)];
                    }
                }
            }

            if (count($rows) > $found) {
                ++$entries;
            }

            // Nothing is written, so a batch is dropped as it's done
            if (self::BATCH_SIZE === ++$batched) {
                $this->manager->clear();
                $batched = 0;
            }
        }

        if ([] === $rows) {
            $io->success('No problems found.');

            return Command::SUCCESS;
        }

        $io->table(['Entry', 'Content', 'Block', 'Field', 'Locale', 'Problem'], $rows);
        $io->error(sprintf('Found %s in %s.', self::count(count($rows), 'problem'), self::count($entries, 'entry', 'entries')));

        return Command::FAILURE;
    }

    /**
     * What's stored of the entry, upgraded: a published entry's published content, and its draft when it has one, or a
     * never-published entry's draft, which is its rows
     *
     * @return \Generator<string, EntryInterface>
     */
    private function contents(EntryInterface $entry): \Generator
    {
        if (!$entry->isPublished()) {
            yield 'draft' => $this->workingCopies->getWorkingCopy($entry);

            return;
        }

        $published = clone $entry;
        $published->setBlockTree($this->upgrader->readBlockTree($entry));

        yield 'published' => $published;

        if ($this->workingCopies->hasDraft($entry)) {
            yield 'draft' => $this->workingCopies->getWorkingCopy($entry);
        }
    }

    private static function count(int $count, string $noun, ?string $plural = null): string
    {
        return sprintf('%d %s', $count, 1 === $count ? $noun : $plural ?? $noun . 's');
    }
}
