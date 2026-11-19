<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer\Import;

use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * Writes an import's result, with its whole plan, as plain JSON data and reads it back, so an import started in the
 * admin keeps its dry run's result and its own for the admin user to see (Model\ImportInterface). The plan's records are
 * keyed by their kind, as RecordKind's values. The messages are kept as their keys, parameters and domains, and
 * translated when they're shown. See "Who runs it" in docs/spec/transfer.md
 */
final readonly class ImportResultNormalizer
{
    /**
     * @return array<string, mixed>
     *
     * @throws \InvalidArgumentException for a message that isn't a Symfony TranslatableMessage
     */
    public function normalize(ImportResult $result): array
    {
        return [
            'status' => $result->status->value,
            'invalid' => $result->invalid,
            'plan' => null === $result->plan ? null : self::normalizePlan($result->plan),
        ];
    }

    /**
     * @param array<array-key, mixed> $data what normalize() wrote
     *
     * @throws \InvalidArgumentException when it isn't
     */
    public function denormalize(array $data): ImportResult
    {
        $status = ImportStatus::tryFrom(self::string($data, 'status'));
        $plan = $data['plan'] ?? null;

        return match (true) {
            ImportStatus::Invalid === $status => ImportResult::invalid(self::string($data, 'invalid')),
            !is_array($plan) => throw new \InvalidArgumentException('The result has no plan'),
            ImportStatus::Applied === $status, ImportStatus::WouldApply === $status => ImportResult::applied(self::denormalizePlan($plan), ImportStatus::WouldApply === $status),
            ImportStatus::Refused === $status => ImportResult::refused(self::denormalizePlan($plan)),
            default => throw new \InvalidArgumentException('The result has no known status'),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function normalizePlan(ImportPlan $plan): array
    {
        $records = [];
        foreach (RecordKind::cases() as $kind) {
            $records[$kind->value] = array_map(self::normalizeRecord(...), $plan->records($kind));
        }

        return [
            'records' => $records,
            'conflicts' => array_map(static fn (ImportConflict $conflict): array => [
                'kind' => $conflict->kind->value,
                'uuid' => $conflict->uuid,
                'message' => self::normalizeMessage($conflict->message),
            ], $plan->conflicts),
            'refusals' => array_map(static fn (ImportRefusal $refusal): array => [
                'uuid' => $refusal->uuid,
                'reason' => self::normalizeMessage($refusal->reason),
            ], $plan->refusals),
            'homepageChanges' => array_map(static fn (HomepageChange $change): array => [
                'channelCode' => $change->channelCode,
                'from' => $change->from,
                'fromTitle' => $change->fromTitle,
                'to' => $change->to,
                'toTitle' => $change->toTitle,
            ], $plan->homepageChanges),
            'unresolvedReferences' => array_map(static fn (UnresolvedReference $reference): array => [
                'uuid' => $reference->uuid,
                'label' => $reference->label,
                'type' => $reference->type,
                'id' => $reference->id,
            ], $plan->unresolvedReferences),
        ];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function denormalizePlan(array $data): ImportPlan
    {
        $records = [];
        foreach (RecordKind::cases() as $kind) {
            array_push($records, ...self::map(self::array($data, 'records'), $kind->value, static fn (array $record): PlannedRecord => self::denormalizeRecord($kind, $record)));
        }

        return new ImportPlan(
            $records,
            self::map($data, 'conflicts', static fn (array $conflict): ImportConflict => new ImportConflict(
                ConflictKind::tryFrom(self::string($conflict, 'kind')) ?? throw new \InvalidArgumentException('A conflict has no known kind'),
                self::string($conflict, 'uuid'),
                self::denormalizeMessage(self::array($conflict, 'message')),
            )),
            self::map($data, 'refusals', static fn (array $refusal): ImportRefusal => new ImportRefusal(
                self::nullableString($refusal, 'uuid'),
                self::denormalizeMessage(self::array($refusal, 'reason')),
            )),
            self::map($data, 'homepageChanges', static fn (array $change): HomepageChange => new HomepageChange(
                self::string($change, 'channelCode'),
                self::nullableString($change, 'from'),
                self::nullableString($change, 'fromTitle'),
                self::nullableString($change, 'to'),
                self::nullableString($change, 'toTitle'),
            )),
            self::map($data, 'unresolvedReferences', static fn (array $reference): UnresolvedReference => new UnresolvedReference(
                self::string($reference, 'uuid'),
                self::string($reference, 'label'),
                self::string($reference, 'type'),
                self::string($reference, 'id'),
            )),
        );
    }

    /**
     * @return array<string, mixed> without its kind, which it's keyed by
     */
    private static function normalizeRecord(PlannedRecord $record): array
    {
        return [
            'uuid' => $record->uuid,
            'label' => $record->label,
            'picked' => $record->picked,
            'action' => $record->action->value,
            'contentType' => $record->contentType,
        ];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function denormalizeRecord(RecordKind $kind, array $data): PlannedRecord
    {
        $picked = $data['picked'] ?? null;
        if (!is_bool($picked)) {
            throw new \InvalidArgumentException('A record has no picked flag');
        }

        return new PlannedRecord(
            $kind,
            self::string($data, 'uuid'),
            self::string($data, 'label'),
            $picked,
            ImportAction::tryFrom(self::string($data, 'action')) ?? throw new \InvalidArgumentException('A record has no known action'),
            self::nullableString($data, 'contentType'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function normalizeMessage(TranslatableInterface $message): array
    {
        if (!$message instanceof TranslatableMessage) {
            throw new \InvalidArgumentException(sprintf('An import\'s messages are %s, not %s', TranslatableMessage::class, $message::class));
        }

        $parameters = [];
        foreach ($message->getParameters() as $name => $value) {
            $parameters[$name] = $value instanceof TranslatableInterface ? self::normalizeMessage($value) : $value;
        }

        return ['message' => $message->getMessage(), 'parameters' => $parameters, 'domain' => $message->getDomain()];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function denormalizeMessage(array $data): TranslatableMessage
    {
        $parameters = [];
        foreach (self::array($data, 'parameters') as $name => $value) {
            $parameters[$name] = is_array($value) ? self::denormalizeMessage($value) : $value;
        }

        return new TranslatableMessage(self::string($data, 'message'), $parameters, self::nullableString($data, 'domain'));
    }

    /**
     * @template T
     *
     * @param array<array-key, mixed> $data
     * @param callable(array<array-key, mixed>): T $denormalize
     *
     * @return list<T>
     */
    private static function map(array $data, string $key, callable $denormalize): array
    {
        $items = [];
        foreach (self::array($data, $key) as $item) {
            if (!is_array($item)) {
                throw new \InvalidArgumentException(sprintf('The plan\'s %s aren\'t objects', $key));
            }

            $items[] = $denormalize($item);
        }

        return $items;
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    private static function array(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        return is_array($value) ? $value : throw new \InvalidArgumentException(sprintf('There is no %s', $key));
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function string(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        return is_string($value) ? $value : throw new \InvalidArgumentException(sprintf('There is no %s', $key));
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function nullableString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return null === $value || is_string($value) ? $value : throw new \InvalidArgumentException(sprintf('%s isn\'t a string', $key));
    }
}
