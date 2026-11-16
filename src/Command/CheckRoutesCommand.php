<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Command;

use Doctrine\Persistence\ObjectManager;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Problem\ProblemCountUpdaterInterface;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Routing\TakenUrlFinderInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * A route added after an entry was published silently wins over the entry. This runs every published URL, in each
 * channel and locale it shows in, through the router, lists each one another route now matches, and exits non-zero
 * when it finds any, for CI and deploys. It counts the problems of the published entries again too, as the lists and
 * the Content badge read them, since a route added since may take a URL. See "Routes added later" in
 * docs/spec/urls-and-seo.md, "Console commands" in docs/spec/extending.md and "Problems" in docs/spec/admin.md
 */
#[AsCommand(name: 'setono:cms:check-routes', description: 'Lists every entry URL that another route now matches')]
final class CheckRoutesCommand extends Command
{
    private const BATCH_SIZE = 100;

    /**
     * @param ObjectManager $manager the manager of entries
     */
    public function __construct(
        private readonly EntryRepositoryInterface $entries,
        private readonly TakenUrlFinderInterface $takenUrls,
        private readonly ObjectManager $manager,
        private readonly ProblemCountUpdaterInterface $problemCounts,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $rows = [];
        $batched = 0;
        $published = [];
        foreach ($this->entries->iterateAll() as $entry) {
            foreach ($this->takenUrls->findLive($entry) as $taken) {
                $rows[] = [$taken->channelCode ?? '', $taken->localeCode, sprintf('/%s/%s', $taken->localeCode, $taken->path), $taken->route];
            }

            if ($entry->isPublished()) {
                $published[] = $entry;
            }

            // A batch's problems are counted again, and then it's dropped, as nothing else is written
            if (self::BATCH_SIZE === ++$batched) {
                $this->countProblems($published);
                $published = [];
                $this->manager->clear();
                $batched = 0;
            }
        }

        $this->countProblems($published);

        if ([] === $rows) {
            $io->success('No other route matches an entry URL.');

            return Command::SUCCESS;
        }

        $io->table(['Channel', 'Locale', 'URL', 'Route'], $rows);
        $io->error(sprintf('Another route matches %d entry %s, so shoppers get the route\'s page there.', count($rows), 1 === count($rows) ? 'URL' : 'URLs'));

        return Command::FAILURE;
    }

    /**
     * @param list<EntryInterface> $entries
     */
    private function countProblems(array $entries): void
    {
        if ([] !== $entries) {
            $this->problemCounts->update($entries);
        }
    }

}
