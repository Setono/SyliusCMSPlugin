<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Command;

use Setono\SyliusCMSPlugin\Preset\CodePresetExporter;
use Setono\SyliusCMSPlugin\Preset\Preset;
use Setono\SyliusCMSPlugin\Preset\PresetProviderInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Writes an editor preset, by UUID or name, to a code preset file, <dir>/<code>.json, the code being its name in snake
 * case, with media fields left empty. It's how developers compose code presets: they save one in the builder and
 * export it. The directory is made when it isn't there, and a file of the same name is overwritten, so a preset is
 * exported again after it's changed. See "Code presets" in docs/spec/layouts-and-presets.md and "Console commands" in
 * docs/spec/extending.md
 */
#[AsCommand(name: 'setono:cms:preset:export', description: 'Writes an editor preset, by UUID or name, to a code preset file')]
final class ExportPresetCommand extends Command
{
    public function __construct(
        private readonly PresetProviderInterface $presets,
        private readonly CodePresetExporter $exporter,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('preset', InputArgument::REQUIRED, 'The editor preset\'s UUID or name')
            ->addArgument('dir', InputArgument::REQUIRED, 'The directory to write <code>.json in')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /** @var string $id */
        $id = $input->getArgument('preset');
        /** @var string $directory */
        $directory = $input->getArgument('dir');

        $found = $this->find($id);
        if ([] === $found) {
            $io->error(sprintf('There is no editor preset with the UUID or name "%s".', $id));

            return Command::FAILURE;
        }

        if (count($found) > 1) {
            $io->error(sprintf('There are %d editor presets named "%s": %s. Export one by its UUID.', count($found), $id, implode(', ', array_map(static fn (Preset $preset): string => $preset->id(), $found))));

            return Command::FAILURE;
        }

        $file = $this->exporter->export($found[0]);
        $path = rtrim($directory, '/') . '/' . $file->filename();
        if ((!is_dir($directory) && !@mkdir($directory, 0777, true)) || false === @file_put_contents($path, $file->contents)) {
            $io->error(sprintf('The file %s can\'t be written.', $path));

            return Command::FAILURE;
        }

        $io->success(sprintf('Wrote %s.', $path));

        return Command::SUCCESS;
    }

    /**
     * The editor preset with the UUID, or else those with the name
     *
     * @return list<Preset>
     */
    private function find(string $id): array
    {
        $preset = $this->presets->find($id);
        if (null !== $preset && !$preset->isFromCode()) {
            return [$preset];
        }

        return array_values(array_filter($this->presets->all(), static fn (Preset $preset): bool => !$preset->isFromCode() && $preset->name === $id));
    }
}
