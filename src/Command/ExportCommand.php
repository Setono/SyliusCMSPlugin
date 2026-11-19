<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Command;

use Setono\SyliusCMSPlugin\Transfer\DirectoryTarget;
use Setono\SyliusCMSPlugin\Transfer\ExporterInterface;
use Setono\SyliusCMSPlugin\Transfer\ExportPlan;
use Setono\SyliusCMSPlugin\Transfer\ExportPlannerInterface;
use Setono\SyliusCMSPlugin\Transfer\ExportSelection;
use Setono\SyliusCMSPlugin\Transfer\InvalidExportSelectionException;
use Setono\SyliusCMSPlugin\Transfer\ZipTarget;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Exports the picked entries, editor presets and media items, with the snippets and media items they use, to <dir>,
 * which must be empty or missing, or with --zip to <dir>.zip, which must not exist. Options combine, and at least one
 * is required. See "Commands" in docs/spec/transfer.md and "Console commands" in docs/spec/extending.md
 */
#[AsCommand(name: 'setono:cms:export', description: 'Exports entries, editor presets and media items, with what they use, to a folder or a zip')]
final class ExportCommand extends Command
{
    public function __construct(
        private readonly ExportPlannerInterface $planner,
        private readonly ExporterInterface $exporter,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('dir', InputArgument::REQUIRED, 'The folder to write the export to, empty or missing, or with --zip the zip\'s path without .zip')
            ->addOption('entry', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Picks the entry with this UUID')
            ->addOption('content-type', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Picks every entry of the content type with this code')
            ->addOption('media-folder', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Picks every media item in the folder with this UUID and in its subfolders')
            ->addOption('presets', null, InputOption::VALUE_NONE, 'Picks every editor preset')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Picks every entry, editor preset and media item')
            ->addOption('zip', null, InputOption::VALUE_NONE, 'Writes <dir>.zip instead of the folder')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $selection = new ExportSelection(
            self::strings($input->getOption('entry')),
            self::strings($input->getOption('content-type')),
            self::strings($input->getOption('media-folder')),
            allPresets: true === $input->getOption('presets'),
            everything: true === $input->getOption('all'),
        );
        if ($selection->isEmpty()) {
            $io->error('Pick what to export with --entry, --content-type, --media-folder, --presets or --all.');

            return Command::INVALID;
        }

        /** @var string $directory */
        $directory = $input->getArgument('dir');
        $directory = '/' === $directory ? $directory : rtrim($directory, '/');
        $zip = true === $input->getOption('zip');
        $path = $zip ? $directory . '.zip' : $directory;

        if ($zip && file_exists($path)) {
            $io->error(sprintf('%s exists already.', $path));

            return Command::INVALID;
        }

        if (!$zip && file_exists($path) && (!is_dir($path) || [] !== array_diff((array) scandir($path), ['.', '..']))) {
            $io->error(sprintf('%s must be an empty directory or not exist yet.', $path));

            return Command::INVALID;
        }

        try {
            $plan = $this->planner->plan($selection);
        } catch (InvalidExportSelectionException $e) {
            $io->error($e->getMessage());

            return Command::INVALID;
        }

        if ($zip) {
            $target = new ZipTarget($path);
            $this->exporter->export($plan, $target);
            $target->close();
        } else {
            $this->exporter->export($plan, new DirectoryTarget($path));
        }

        $io->success(sprintf(
            'Exported %s, %s and %s to %s.',
            self::count(count($plan->entries()), 'entry', 'entries'),
            self::count(count($plan->presets()), 'editor preset', 'editor presets'),
            self::count(count($plan->mediaItems()), 'media item', 'media items'),
            $path,
        ));

        self::added($io, $plan);

        return Command::SUCCESS;
    }

    /**
     * Says what came along because the picked records use it
     */
    private static function added(SymfonyStyle $io, ExportPlan $plan): void
    {
        $added = array_filter([
            0 === count($plan->addedEntries()) ? null : self::count(count($plan->addedEntries()), 'snippet', 'snippets'),
            0 === count($plan->addedMediaItems()) ? null : self::count(count($plan->addedMediaItems()), 'media item', 'media items'),
        ]);

        if ([] !== $added) {
            $io->text(sprintf('%s came along because the picked records use them.', implode(' and ', $added)));
        }
    }

    private static function count(int $count, string $one, string $many): string
    {
        return sprintf('%d %s', $count, 1 === $count ? $one : $many);
    }

    /**
     * @return list<string>
     */
    private static function strings(mixed $values): array
    {
        return is_array($values) ? array_values(array_filter($values, is_string(...))) : [];
    }
}
