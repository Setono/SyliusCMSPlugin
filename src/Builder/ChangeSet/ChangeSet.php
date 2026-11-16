<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Builder\ChangeSet;

use Setono\SyliusCMSPlugin\Json\ListedObject;
use Setono\SyliusCMSPlugin\Registry\Code;
use Setono\SyliusCMSPlugin\Translation\Fingerprint;
use Setono\SyliusCMSPlugin\Tree\InvalidStoredTreeException;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTreeSerializer;

/**
 * What the builder autosaves: the changes it made since it last loaded, as values by field and locale, where they
 * were translated from, block styles and visibility conditions, blocks added, moved or removed, and the entry's own
 * values, where they were translated from and translations' shown switches.
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
            $change instanceof SetConditions => new ListedObject([
                'op' => 'set_conditions',
                'key' => $change->key,
                'conditions' => array_map(static fn (array $condition): ListedObject => new ListedObject([
                    'kind' => $condition['kind'],
                    'negate' => $condition['negate'],
                    'params' => (object) $condition['params'],
                ]), $change->conditions),
            ]),
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
            $change instanceof SetTranslated => new ListedObject([
                'op' => 'set_translated',
                'key' => $change->key,
                'field' => $change->field,
                'locale' => $change->locale,
                'translated' => self::source($change->translated),
            ]),
            $change instanceof SetEntryTranslated => new ListedObject([
                'op' => 'set_entry_translated',
                'field' => $change->field,
                'locale' => $change->locale,
                'translated' => self::source($change->translated),
            ]),
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
            'set_conditions' => new SetConditions(self::string($change, 'key'), self::conditions($change)),
            'set_entry_value' => new SetEntryValue(
                self::string($change, 'field'),
                self::nullableString($change, 'locale'),
                array_key_exists('value', $change) ? $change['value'] : throw new InvalidChangeSetException('A set_entry_value change must have a value'),
            ),
            'set_shown' => new SetShown(
                self::string($change, 'locale'),
                is_bool($change['shown'] ?? null) ? $change['shown'] : throw new InvalidChangeSetException('A set_shown change must have a shown switch that is true or false'),
            ),
            'set_translated' => new SetTranslated(
                self::string($change, 'key'),
                self::string($change, 'field'),
                self::string($change, 'locale'),
                self::translated($change),
            ),
            'set_entry_translated' => new SetEntryTranslated(
                self::string($change, 'field'),
                self::string($change, 'locale'),
                self::translated($change),
            ),
            default => throw new InvalidChangeSetException('A change must have the op add_block, move_block, remove_block, set_value, set_style, set_conditions, set_entry_value, set_shown, set_translated or set_entry_translated'),
        };
    }

    /**
     * Where a value was translated from: the locale and the fingerprint of the value it was translated from, or null
     * when it has no source
     *
     * @param array<array-key, mixed> $change
     *
     * @return array{from: string, fingerprint: string}|null
     */
    private static function translated(array $change): ?array
    {
        if (!array_key_exists('translated', $change)) {
            throw new InvalidChangeSetException('A change to the translation state must have a translated');
        }

        $translated = $change['translated'];
        if (null === $translated) {
            return null;
        }

        $from = is_array($translated) ? $translated['from'] ?? null : null;
        $fingerprint = is_array($translated) ? $translated['fingerprint'] ?? null : null;
        if (!is_string($from) || '' === $from || !is_string($fingerprint) || !Fingerprint::isValid($fingerprint)) {
            throw new InvalidChangeSetException('The "translated" of a change must be null, or have the locale the value was translated from and its fingerprint');
        }

        return ['from' => $from, 'fingerprint' => $fingerprint];
    }

    /**
     * @param array{from: string, fingerprint: string}|null $translated
     */
    private static function source(?array $translated): ?ListedObject
    {
        return null === $translated ? null : new ListedObject(['from' => $translated['from'], 'fingerprint' => $translated['fingerprint']]);
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
     * The conditions of a set_conditions change, each with a condition kind's code, whether it's negated and its
     * parameter values. A kind the code doesn't have is kept, as a block's conditions are saved whole
     *
     * @param array<array-key, mixed> $change
     *
     * @return list<array{kind: string, negate: bool, params: array<string, mixed>}>
     */
    private static function conditions(array $change): array
    {
        $conditions = $change['conditions'] ?? null;
        if (!is_array($conditions) || !array_is_list($conditions)) {
            throw new InvalidChangeSetException('A set_conditions change must have a list of conditions');
        }

        return array_map(static function (mixed $condition): array {
            if (!is_array($condition)) {
                throw new InvalidChangeSetException('A condition must be an object');
            }

            $kind = $condition['kind'] ?? null;
            if (!is_string($kind) || !Code::isValid($kind)) {
                throw new InvalidChangeSetException('A condition must have a condition kind\'s code as its kind');
            }

            $negate = $condition['negate'] ?? null;
            if (!is_bool($negate)) {
                throw new InvalidChangeSetException('The negate of a condition must be true or false');
            }

            $params = $condition['params'] ?? null;
            // An empty JSON object, as CanonicalJson::decode() reads it
            if ($params instanceof \stdClass) {
                $params = get_object_vars($params);
            }

            if (!is_array($params) || ([] !== $params && array_is_list($params))) {
                throw new InvalidChangeSetException('The params of a condition must be an object');
            }

            /** @var array<string, mixed> $params */
            return ['kind' => $kind, 'negate' => $negate, 'params' => $params];
        }, $conditions);
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
