<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Builder\ChangeSet;

use Setono\SyliusCMSPlugin\Tree\InvalidStoredTreeException;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTreeSerializer;

/**
 * What the builder autosaves: the changes it made since it last loaded, as values by field and locale,
 * and blocks added or removed. See "Saving" in docs/spec/builder.md. The body format is internal to the builder
 */
final readonly class ChangeSet
{
    /**
     * @param list<Change> $changes in the order they were made
     */
    public function __construct(public array $changes)
    {
    }

    /**
     * @throws InvalidChangeSetException
     */
    public static function fromArray(mixed $data): self
    {
        if (!is_array($data) || !isset($data['changes']) || !is_array($data['changes']) || !array_is_list($data['changes'])) {
            throw new InvalidChangeSetException('A change set must be an object with a list of changes');
        }

        $serializer = new StoredTreeSerializer();

        return new self(array_map(static fn (mixed $change): Change => self::change($change, $serializer), $data['changes']));
    }

    private static function change(mixed $change, StoredTreeSerializer $serializer): Change
    {
        if (!is_array($change)) {
            throw new InvalidChangeSetException('A change must be an object');
        }

        return match ($change['op'] ?? null) {
            'add_block' => new AddBlock(
                self::nullableString($change, 'parent'),
                self::index($change),
                self::block($change, $serializer),
            ),
            'remove_block' => new RemoveBlock(self::string($change, 'key')),
            'set_value' => new SetValue(
                self::string($change, 'key'),
                self::string($change, 'field'),
                self::nullableString($change, 'locale'),
                array_key_exists('value', $change) ? $change['value'] : throw new InvalidChangeSetException('A set_value change must have a value'),
            ),
            default => throw new InvalidChangeSetException('A change must have the op add_block, remove_block or set_value'),
        };
    }

    /**
     * @param array<array-key, mixed> $change
     */
    private static function string(array $change, string $key): string
    {
        $value = $change[$key] ?? null;
        if (!is_string($value) || '' === $value) {
            throw new InvalidChangeSetException(sprintf('The "%s" of a change must be a non-empty string', $key));
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $change
     */
    private static function nullableString(array $change, string $key): ?string
    {
        return null === ($change[$key] ?? null) ? null : self::string($change, $key);
    }

    /**
     * @param array<array-key, mixed> $change
     */
    private static function index(array $change): int
    {
        $index = $change['index'] ?? null;
        if (!is_int($index) || $index < 0) {
            throw new InvalidChangeSetException('The "index" of an add_block change must be 0 or more');
        }

        return $index;
    }

    /**
     * @param array<array-key, mixed> $change
     */
    private static function block(array $change, StoredTreeSerializer $serializer): StoredBlock
    {
        try {
            return $serializer->denormalizeBlock($change['block'] ?? null);
        } catch (InvalidStoredTreeException $e) {
            throw new InvalidChangeSetException(sprintf('The block of an add_block change is invalid: %s', $e->getMessage()), previous: $e);
        }
    }
}
