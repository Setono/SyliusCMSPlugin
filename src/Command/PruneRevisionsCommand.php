<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Command;

use Setono\SyliusCMSPlugin\Revision\RevisionPruner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Deletes the revisions beyond revisions.keep per entry. A project runs it from cron. See "Console commands" in
 * docs/spec/extending.md
 */
#[AsCommand(name: 'setono:cms:revisions:prune', description: 'Deletes the revisions beyond setono_sylius_cms.revisions.keep per entry')]
final class PruneRevisionsCommand extends Command
{
    public function __construct(private readonly RevisionPruner $pruner)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $pruned = $this->pruner->prune();

        if (0 === $pruned) {
            $io->success(sprintf('Nothing to prune: no entry has more than %d revisions.', $this->pruner->keep));
        } else {
            $io->success(sprintf('Deleted %d revision%s. Every entry keeps its newest %d.', $pruned, 1 === $pruned ? '' : 's', $this->pruner->keep));
        }

        return Command::SUCCESS;
    }
}
