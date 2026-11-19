<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Command;

use Setono\SyliusCMSPlugin\Transfer\Format\InvalidExportException;
use Setono\SyliusCMSPlugin\Transfer\Import\ExportSources;
use Setono\SyliusCMSPlugin\Transfer\Import\ImportAction;
use Setono\SyliusCMSPlugin\Transfer\Import\ImporterInterface;
use Setono\SyliusCMSPlugin\Transfer\Import\ImportOptions;
use Setono\SyliusCMSPlugin\Transfer\Import\ImportPlan;
use Setono\SyliusCMSPlugin\Transfer\Import\ImportResult;
use Setono\SyliusCMSPlugin\Transfer\Import\ImportStatus;
use Setono\SyliusCMSPlugin\Transfer\Import\PlannedRecord;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Imports an export folder or zip. It prints the plan, and the reasons when it refuses, and exits with the code the
 * result maps to (ImportResult::exitCode()), so a dry run works as a gate in CI and deploys. See "Commands" in
 * docs/spec/transfer.md and "Console commands" in docs/spec/extending.md
 */
#[AsCommand(name: 'setono:cms:import', description: 'Imports an export folder or zip: picked entries land in their drafts, all or nothing')]
final class ImportCommand extends Command
{
    private const ACTIONS = [
        'created' => ImportAction::Create,
        'updated' => ImportAction::Update,
        'unchanged' => ImportAction::Unchanged,
        'left alone' => ImportAction::LeaveAlone,
    ];

    public function __construct(
        private readonly ImporterInterface $importer,
        private readonly TranslatorInterface $translator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('path', InputArgument::REQUIRED, 'The export folder or zip')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Prints the plan and changes nothing, exiting as the real run would')
            ->addOption('publish', null, InputOption::VALUE_NONE, 'Publishes every entry the import writes, as part of the import')
            ->addOption('overwrite', null, InputOption::VALUE_NONE, 'Overwrites the records the plan lists as conflicts, discarding their drafts. It doesn\'t resolve URL clashes')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string $path */
        $path = $input->getArgument('path');

        try {
            $source = ExportSources::open($path);
        } catch (InvalidExportException $e) {
            $io->error($e->getMessage());

            return Command::INVALID;
        }

        $options = new ImportOptions(
            true === $input->getOption('dry-run'),
            true === $input->getOption('publish'),
            true === $input->getOption('overwrite'),
        );
        $result = $this->importer->import($source, $options);

        if (null !== $result->plan) {
            $this->printPlan($io, $result->plan, $options->overwrite);
        }

        $this->printResult($io, $result, $options);

        return $result->exitCode();
    }

    private function printPlan(SymfonyStyle $io, ImportPlan $plan, bool $overwrite): void
    {
        foreach ($plan->kinds() as $kind) {
            $io->section(ucfirst($kind->nouns()[1]));
            $io->listing(array_map(static fn (PlannedRecord $record): string => sprintf(
                '%s%s%s: %s',
                $record->label,
                null === $record->contentType ? '' : sprintf(' (%s)', $record->contentType),
                $record->picked ? '' : ', came along',
                str_replace('_', ' ', $record->action->value),
            ), $plan->records($kind)));
        }

        if ([] !== $plan->homepageChanges) {
            $io->section('Homepage changes');
            $changes = [];
            foreach ($plan->homepageChanges as $change) {
                $changes[] = null === $change->to
                    ? sprintf('%s: %s stops being its homepage', $change->channelCode, (string) $change->fromTitle)
                    : sprintf('%s: %s becomes its homepage%s', $change->channelCode, (string) $change->toTitle, null === $change->fromTitle ? '' : sprintf(', instead of %s', $change->fromTitle));
            }

            $io->listing($changes);
        }

        if ([] !== $plan->unresolvedReferences) {
            $io->section('References that don\'t resolve here');
            $references = [];
            foreach ($plan->unresolvedReferences as $reference) {
                $references[] = sprintf('%s: %s "%s"', $reference->label, str_replace('_', ' ', $reference->type), $reference->id);
            }

            $io->listing($references);
            $io->text('They import unchanged, and the admin flags them as broken references.');
        }

        $conflicts = $overwritten = [];
        foreach ($plan->conflicts as $conflict) {
            if ($overwrite && $conflict->kind->isResolvedByOverwrite()) {
                $overwritten[] = $conflict->message->trans($this->translator);
            } else {
                $conflicts[] = $conflict->message->trans($this->translator);
            }
        }

        foreach (['Conflicts' => $conflicts, 'Overwritten' => $overwritten] as $heading => $messages) {
            if ([] !== $messages) {
                $io->section($heading);
                $io->listing($messages);
            }
        }
    }

    private function printResult(SymfonyStyle $io, ImportResult $result, ImportOptions $options): void
    {
        $plan = $result->plan;
        if (ImportStatus::Invalid === $result->status || null === $plan) {
            $io->error((string) $result->invalid);

            return;
        }

        if (ImportStatus::Refused === $result->status) {
            $reasons = [];
            foreach ($plan->refusals as $refusal) {
                $reasons[] = $refusal->reason->trans($this->translator);
            }

            if ($plan->isRefusedOnlyForConflictsOverwritingResolves()) {
                $reasons[] = 'Run it with --overwrite to overwrite the records in conflict, discarding their drafts.';
            } elseif ([] !== $plan->conflicts) {
                $reasons[] = 'It refused because of the conflicts above.';
            }

            $io->error(sprintf('%s refused. Nothing changed.', $options->dryRun ? 'The dry run' : 'The import'));
            $io->listing($reasons);

            return;
        }

        $io->success(sprintf('%s: %s.', ImportStatus::WouldApply === $result->status ? 'The dry run would import' : 'Imported', self::summary($plan)));
    }

    /**
     * Such as "1 entry updated, 2 entries, 1 editor preset and 3 media items created"
     */
    private static function summary(ImportPlan $plan): string
    {
        $parts = [];
        foreach (self::ACTIONS as $word => $action) {
            $counts = [];
            foreach ($plan->kinds() as $kind) {
                $count = count(array_filter($plan->records($kind), static fn (PlannedRecord $record): bool => $action === $record->action));
                if ($count > 0) {
                    [$one, $many] = $kind->nouns();
                    $counts[] = sprintf('%d %s', $count, 1 === $count ? $one : $many);
                }
            }

            if ([] !== $counts) {
                $parts[] = sprintf('%s %s', self::listed($counts), $word);
            }
        }

        return [] === $parts ? 'nothing to import' : implode(', ', $parts);
    }

    /**
     * Such as "2 entries, 1 editor preset and 3 media items"
     *
     * @param non-empty-list<string> $items
     */
    private static function listed(array $items): string
    {
        $last = array_pop($items);

        return [] === $items ? $last : sprintf('%s and %s', implode(', ', $items), $last);
    }
}
