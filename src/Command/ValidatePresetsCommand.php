<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Command;

use Setono\SyliusCMSPlugin\Preset\CodePresetFiles;
use Setono\SyliusCMSPlugin\Preset\InvalidPresetException;
use Setono\SyliusCMSPlugin\Preset\Preset;
use Setono\SyliusCMSPlugin\Preset\PresetCheck;
use Setono\SyliusCMSPlugin\Preset\PresetIssue;
use Setono\SyliusCMSPlugin\Preset\PresetSerializer;
use Setono\SyliusCMSPlugin\Registry\Code;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Validates code preset files, for CI: every file in every directory of setono_sylius_cms.presets.paths, disabled ones
 * included, or the files and directories given. It checks the format, that every block type exists, that no
 * block-type version is newer than the code's, the nesting rules, that values fit their field types, and that a
 * starting point's content type and layout exist (PresetCheck), and exits non-zero on any error. A block of a disabled
 * type isn't an error, as the type exists. See "Code presets" in docs/spec/layouts-and-presets.md and "Console commands"
 * in docs/spec/extending.md
 */
#[AsCommand(name: 'setono:cms:preset:validate', description: 'Validates code preset files, by default in every registered directory')]
final class ValidatePresetsCommand extends Command
{
    private readonly PresetSerializer $serializer;

    /**
     * @param array<string, string> $files the registered code preset files: code => path
     */
    public function __construct(
        private readonly array $files,
        private readonly PresetCheck $check,
        private readonly TranslatorInterface $translator,
    ) {
        parent::__construct();

        $this->serializer = new PresetSerializer();
    }

    protected function configure(): void
    {
        $this->addArgument('paths', InputArgument::IS_ARRAY, 'Preset files, or directories of them, to validate instead of every registered one');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /** @var list<string> $paths */
        $paths = $input->getArgument('paths');

        $invalid = 0;
        $files = [] === $paths ? array_map(static fn (string $path): ?string => null, array_flip($this->files)) : $this->filesAt($paths);
        $presets = [];
        $errorsByFile = [];
        foreach ($files as $path => $error) {
            $path = (string) $path;
            $errorsByFile[$path] = null === $error ? $this->errorsOf($path, $presets) : [$error];
        }

        foreach (self::defaultsSharingAContentType($presets) as $path => $error) {
            $errorsByFile[$path][] = $error;
        }

        foreach ($errorsByFile as $path => $errors) {
            if ([] === $errors) {
                $io->writeln(sprintf(' <info>✔</info> %s', $path));

                continue;
            }

            ++$invalid;
            $io->writeln(sprintf(' <error>✘</error> %s', $path));
            $io->listing($errors);
        }

        $count = count($files);
        if (0 === $invalid) {
            $io->success(sprintf('%d preset %s valid.', $count, 1 === $count ? 'file is' : 'files are'));

            return Command::SUCCESS;
        }

        $io->error(sprintf('%d of %d preset %s errors.', $invalid, $count, 1 === $count ? 'file has' : 'files have'));

        return Command::FAILURE;
    }

    /**
     * The files at the paths given, each with the error that keeps it from being read, or null: a directory gives every
     * *.json file directly in it
     *
     * @param list<string> $paths
     *
     * @return array<string, string|null> path => error
     */
    private function filesAt(array $paths): array
    {
        $files = [];
        foreach ($paths as $path) {
            if (is_dir($path)) {
                try {
                    $files = [...$files, ...array_map(static fn (string $file): ?string => null, array_flip(CodePresetFiles::inDirectory($path)))];
                } catch (\LogicException $e) {
                    $files[$path] = $e->getMessage();
                }

                continue;
            }

            if (!is_file($path)) {
                $files[$path] = 'There is no file or directory here';

                continue;
            }

            $files[$path] = Code::isValid(basename($path, '.json')) ? null : sprintf('A code preset file must be named by its code, which matches %s', trim(Code::PATTERN, '/'));
        }

        return $files;
    }

    /**
     * Only one code preset can be a content type's default starting point, which the create dialog picks first. Each
     * of those that are for the same content type gets an error naming the others
     *
     * @param array<string, Preset> $presets the presets read, by path
     *
     * @return iterable<string, string> path => error
     */
    private static function defaultsSharingAContentType(array $presets): iterable
    {
        $defaults = [];
        foreach ($presets as $path => $preset) {
            if (true === $preset->startingPoint?->default) {
                $defaults[$preset->startingPoint->contentType][] = $path;
            }
        }

        foreach ($defaults as $contentType => $paths) {
            if (count($paths) < 2) {
                continue;
            }

            foreach ($paths as $path) {
                $others = array_map(basename(...), array_values(array_diff($paths, [$path])));
                yield $path => sprintf('It\'s the default starting point of the content type "%s", as %s %s too. Only one code preset can be.', $contentType, implode(', ', $others), 1 === count($others) ? 'is' : 'are');
            }
        }
    }

    /**
     * @param array<string, Preset> $presets the presets read so far, by path, which it adds the file's to
     *
     * @return list<string>
     */
    private function errorsOf(string $path, array &$presets): array
    {
        try {
            $preset = $this->serializer->fromJson((string) file_get_contents($path), basename($path, '.json'));
        } catch (InvalidPresetException $e) {
            return [$e->getMessage()];
        }

        $presets[$path] = $preset;

        $errors = [];
        foreach ($this->check->check($preset) as $issue) {
            if ($issue->kind->failsValidation()) {
                $errors[] = $this->describe($issue);
            }
        }

        return $errors;
    }

    private function describe(PresetIssue $issue): string
    {
        $message = $issue->message->trans($this->translator);

        return null === $issue->block ? $message : sprintf('%s (block %s)', $message, $issue->block);
    }
}
