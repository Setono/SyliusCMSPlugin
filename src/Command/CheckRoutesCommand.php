<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Command;

use Doctrine\Persistence\ObjectManager;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Routing\Slug;
use Setono\SyliusCMSPlugin\Routing\TakenUrlFinderInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * A route added after an entry was published silently wins over the entry. This runs every published URL, in each
 * channel and locale it shows in, through the router, lists each one another route now matches, and exits non-zero
 * when it finds any, for CI and deploys. See "Routes added later" in docs/spec/urls-and-seo.md and "Console commands"
 * in docs/spec/extending.md
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
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $rows = [];
        $batched = 0;
        foreach ($this->entries->iterateAll() as $entry) {
            foreach ($this->takenUrls->find($entry, self::publishedSlugs($entry), $entry->getChannelCodes(), true) as $taken) {
                $rows[] = [$taken->channelCode ?? '', $taken->localeCode, sprintf('/%s/%s', $taken->localeCode, $taken->path), $taken->route];
            }

            // Nothing is written, so a batch is dropped as it's done
            if (self::BATCH_SIZE === ++$batched) {
                $this->manager->clear();
                $batched = 0;
            }
        }

        if ([] === $rows) {
            $io->success('No other route matches an entry URL.');

            return Command::SUCCESS;
        }

        $io->table(['Channel', 'Locale', 'URL', 'Route'], $rows);
        $io->error(sprintf('Another route matches %d entry %s, so shoppers get the route\'s page there.', count($rows), 1 === count($rows) ? 'URL' : 'URLs'));

        return Command::FAILURE;
    }

    /**
     * The slugs of a published entry, which its rows hold. A never-published entry and one in no channel show nowhere
     *
     * @return array<string, string> locale => slug
     */
    private static function publishedSlugs(EntryInterface $entry): array
    {
        if (!$entry->isPublished() || [] === $entry->getChannelCodes()) {
            return [];
        }

        $slugs = [];
        foreach ($entry->getTranslations() as $localeCode => $translation) {
            $slug = $translation->getSlug();
            if (null !== $slug && Slug::isValid($slug)) {
                $slugs[$localeCode] = $slug;
            }
        }

        return $slugs;
    }
}
