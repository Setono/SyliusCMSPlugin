<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Builder\ChangeSet;

use Setono\SyliusCMSPlugin\Json\ListedObject;
use Setono\SyliusCMSPlugin\Tree\InvalidStoredTreeException;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTreeSerializer;

/**
 * What the builder autosaves: the changes it made since it last loaded, as values by field and locale,
 * block styles, blocks added, moved or removed, and the entry's own values and translations' shown switches.
 * See "Saving" in docs/spec/builder.md. The body format is internal to the builder
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

    /**
     * The change set in the format fromArray() reads, to encode as canonical JSON
     *
     * @return array{changes: list<ListedObject>}
     */
    public function toArray(): array
    {
        $serializer = new StoredTreeSerializer();

        return ['changes' => array_map(static fn (Change $change): ListedObject => match (true) {
            $change instanceof AddBlock => new ListedObject([
                'op' => 'add_block',
                'parent' => $change->parent,
                'index' => $change->index,
                'after' => $change->after,
                'block' => $serializer->normalizeBlock($change->block),
            ]),
            $change instanceof MoveBlock => new ListedObject([
                'op' => 'move_block',
                'key' => $change->key,
                'parent' => $change->parent,
                'index' => $change->index,
                'after' => $change->after,
            ]),
            $change instanceof RemoveBlock => new ListedObject(['op' => 'remove_block', 'key' => $change->key]),
            $change instanceof SetStyle => new ListedObject(['op' => 'set_style', 'key' => $change->key, 'style' => $change->style]),
            $change instanceof SetValue => new ListedObject([
                'op' => 'set_value',
                'key' => $change->key,
                'field' => $change->field,
                'locale' => $change->locale,
                'value' => $change->value,
            ]),
            $change instanceof SetEntryValue => new ListedObject([
                'op' => 'set_entry_value',
                'field' => $change->field,
                'locale' => $change->locale,
                'value' => $change->value,
            ]),
            $change instanceof SetShown => new ListedObject(['op' => 'set_shown', 'locale' => $change->locale, 'shown' => $change->shown]),
            default => throw new \LogicException(sprintf('Unknown change %s', $change::class)),
        }, $this->changes)];
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
                self::nullableString($change, 'after'),
            ),
            'move_block' => new MoveBlock(
                self::string($change, 'key'),
                self::nullableString($change, 'parent'),
                self::index($change),
                self::nullableString($change, 'after'),
            ),
            'remove_block' => new RemoveBlock(self::string($change, 'key')),
            'set_value' => new SetValue(
                self::string($change, 'key'),
                self::string($change, 'field'),
                self::nullableString($change, 'locale'),
                array_key_exists('value', $change) ? $change['value'] : throw new InvalidChangeSetException('A set_value change must have a value'),
            ),
            'set_style' => new SetStyle(
                self::string($change, 'key'),
                array_key_exists('style', $change) ? self::nullableString($change, 'style') : throw new InvalidChangeSetException('A set_style change must have a style'),
            ),
            'set_entry_value' => new SetEntryValue(
                self::string($change, 'field'),
                self::nullableString($change, 'locale'),
                array_key_exists('value', $change) ? $change['value'] : throw new InvalidChangeSetException('A set_entry_value change must have a value'),
            ),
            'set_shown' => new SetShown(
                self::string($change, 'locale'),
                is_bool($change['shown'] ?? null) ? $change['shown'] : throw new InvalidChangeSetException('A set_shown change must have a shown switch that is true or false'),
            ),
            default => throw new InvalidChangeSetException('A change must have the op add_block, move_block, remove_block, set_value, set_style, set_entry_value or set_shown'),
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
            throw new InvalidChangeSetException('The "index" of a change must be 0 or more');
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
