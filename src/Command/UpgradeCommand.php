<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Command;

use Doctrine\Persistence\ObjectManager;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Upgrade\EntryRewriterInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Runs the upgraders over all stored data and writes it back: the rows and drafts of every entry. Revisions (#240)
 * and editor presets (#270) join with their tickets. See "Console commands" in docs/spec/extending.md
 */
#[AsCommand(name: 'setono:cms:upgrade', description: 'Runs the upgraders over all stored CMS data and writes it back')]
final class UpgradeCommand extends Command
{
    private const BATCH_SIZE = 100;

    /**
     * @param ObjectManager $manager the manager of entries and drafts
     */
    public function __construct(
        private readonly EntryRepositoryInterface $entries,
        private readonly EntryRewriterInterface $rewriter,
        private readonly ContentTypeRegistryInterface $contentTypes,
        private readonly ObjectManager $manager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Reports what would be upgraded, and writes nothing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = true === $input->getOption('dry-run');
        $verb = $dryRun ? 'Would upgrade' : 'Upgraded';

        $blockTrees = $drafts = $batched = 0;
        foreach ($this->entries->iterateAll() as $entry) {
            $rewrite = $this->rewriter->rewrite($entry);
            $what = sprintf('the %s %s', $this->contentTypes->getByEntry($entry)->code, $entry->getUuid());

            if ($rewrite->blockTree) {
                ++$blockTrees;
                $io->writeln(sprintf('%s the block tree of %s', $verb, $what));
            }

            if ($rewrite->draft) {
                ++$drafts;
                $io->writeln(sprintf('%s the draft of %s', $verb, $what));
            }

            foreach ($rewrite->newer as $newer) {
                $io->warning(sprintf('In %s: %s. It\'s kept as it is', $what, $newer));
            }

            if (self::BATCH_SIZE === ++$batched) {
                $this->writeBatch($dryRun);
                $batched = 0;
            }
        }

        $this->writeBatch($dryRun);

        $counts = sprintf('%s and %s', self::count($blockTrees, 'block tree'), self::count($drafts, 'draft'));
        if (0 === $blockTrees + $drafts) {
            $io->success('Everything is up to date.');
        } elseif ($dryRun) {
            $io->success(sprintf('Dry run: %s would be upgraded. Nothing was written.', $counts));
        } else {
            $io->success(sprintf('Upgraded %s.', $counts));
        }

        return Command::SUCCESS;
    }

    /**
     * Writes the batch, unless it's a dry run, and clears the manager, which drops a dry run's changes
     */
    private function writeBatch(bool $dryRun): void
    {
        if (!$dryRun) {
            $this->manager->flush();
        }

        $this->manager->clear();
    }

    private static function count(int $count, string $noun): string
    {
        return sprintf('%d %s%s', $count, $noun, 1 === $count ? '' : 's');
    }
}
