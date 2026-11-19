<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer\Import;

use Symfony\Component\Console\Command\Command;

/**
 * How an import ended, with its plan. A refusal is a result, not an error, so the admin's import on the Messenger
 * worker records it rather than Messenger retrying it (#281). See "Who runs it" and "Commands" in
 * docs/spec/transfer.md
 */
final readonly class ImportResult
{
    /** setono:cms:import's exit code when it refused only because of conflicts that --overwrite would resolve */
    public const EXIT_CONFLICTS = 3;

    /**
     * @param ImportPlan|null $plan null for an export it can't read
     * @param string|null $invalid why it can't read the export, in English, as the code says it
     */
    private function __construct(
        public ImportStatus $status,
        public ?ImportPlan $plan,
        public ?string $invalid = null,
    ) {
    }

    public static function applied(ImportPlan $plan, bool $dryRun): self
    {
        return new self($dryRun ? ImportStatus::WouldApply : ImportStatus::Applied, $plan);
    }

    public static function refused(ImportPlan $plan): self
    {
        return new self(ImportStatus::Refused, $plan);
    }

    public static function invalid(string $reason): self
    {
        return new self(ImportStatus::Invalid, null, $reason);
    }

    /**
     * The exit code setono:cms:import exits with: 0 when it applied or the dry run would, 1 when it refused for a reason
     * --overwrite doesn't fix, 2 (Command::INVALID) for what isn't an export it reads, and 3 when it refused only
     * because of conflicts --overwrite would resolve
     */
    public function exitCode(): int
    {
        return match ($this->status) {
            ImportStatus::Applied, ImportStatus::WouldApply => Command::SUCCESS,
            ImportStatus::Invalid => Command::INVALID,
            ImportStatus::Refused => true === $this->plan?->isRefusedOnlyForConflictsOverwritingResolves() ? self::EXIT_CONFLICTS : Command::FAILURE,
        };
    }

    /**
     * @return list<string> the UUIDs of the records it created, or a dry run would, entries first
     */
    public function created(): array
    {
        return $this->plan?->uuids(ImportAction::Create) ?? [];
    }

    /**
     * @return list<string> the UUIDs of the records it updated, or a dry run would, entries first
     */
    public function updated(): array
    {
        return $this->plan?->uuids(ImportAction::Update) ?? [];
    }

    /**
     * @return list<string> the UUIDs of the records the target has identical, which it skipped
     */
    public function unchanged(): array
    {
        return $this->plan?->uuids(ImportAction::Unchanged) ?? [];
    }

    /**
     * @return list<UnresolvedReference>
     */
    public function unresolvedReferences(): array
    {
        return $this->plan?->unresolvedReferences ?? [];
    }
}
