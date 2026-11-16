<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Builder\ChangeSet;

use Setono\SyliusCMSPlugin\Json\ListedObject;
use Setono\SyliusCMSPlugin\Registry\Code;
use Setono\SyliusCMSPlugin\Translation\Fingerprint;
use Setono\SyliusCMSPlugin\Tree\InvalidStoredTreeException;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredLayout;
use Setono\SyliusCMSPlugin\Tree\StoredTreeSerializer;

/**
 * What the builder autosaves: the changes it made since it last loaded, as values by field and locale, where they
 * were translated from, block styles and visibility conditions, blocks added, moved or removed, the entry's own
 * values, where they were translated from and translations' shown switches, and the layout switched to, its values and
 * where they were translated from.
 * See "Saving" in docs/spec/builder.md. The body format is internal to the builder
 */
final readonly class ChangeSet
{
    /** The ops of the changes, which fromArray() reads them by */
    private const OPS = [
        AddBlock::OP,
        MoveBlock::OP,
        RemoveBlock::OP,
        SetValue::OP,
        SetStyle::OP,
        SetConditions::OP,
        SetEntryValue::OP,
        SetShown::OP,
        SetTranslated::OP,
        SetEntryTranslated::OP,
        SetLayout::OP,
        SetLayoutValue::OP,
        SetLayoutTranslated::OP,
    ];

    /** A locale code, such as en_US, as the shop's URLs allow one */
    private const LOCALE_CODE = '/^[A-Za-z]{2,4}(_([A-Za-z]{4}|[0-9]{3}))?(_([A-Za-z]{2}|[0-9]{3}))?$/';

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

        return ['changes' => array_map(static fn (Change $change): ListedObject => $change->toArray($serializer), $this->changes)];
    }

    private static function change(mixed $change, StoredTreeSerializer $serializer): Change
    {
        if (!is_array($change)) {
            throw new InvalidChangeSetException('A change must be an object');
        }

        return match ($change['op'] ?? null) {
            AddBlock::OP => new AddBlock(
                self::nullableString($change, 'parent'),
                self::index($change),
                self::block($change, $serializer),
                self::nullableString($change, 'after'),
            ),
            MoveBlock::OP => new MoveBlock(
                self::string($change, 'key'),
                self::nullableString($change, 'parent'),
                self::index($change),
                self::nullableString($change, 'after'),
            ),
            RemoveBlock::OP => new RemoveBlock(self::string($change, 'key')),
            SetValue::OP => new SetValue(
                self::string($change, 'key'),
                self::string($change, 'field'),
                self::nullableLocale($change),
                array_key_exists('value', $change) ? $change['value'] : throw new InvalidChangeSetException('A set_value change must have a value'),
            ),
            SetStyle::OP => new SetStyle(
                self::string($change, 'key'),
                array_key_exists('style', $change) ? self::nullableString($change, 'style') : throw new InvalidChangeSetException('A set_style change must have a style'),
            ),
            SetConditions::OP => new SetConditions(self::string($change, 'key'), self::conditions($change)),
            SetEntryValue::OP => new SetEntryValue(
                self::string($change, 'field'),
                self::nullableLocale($change),
                array_key_exists('value', $change) ? $change['value'] : throw new InvalidChangeSetException('A set_entry_value change must have a value'),
            ),
            SetShown::OP => new SetShown(
                self::locale($change),
                is_bool($change['shown'] ?? null) ? $change['shown'] : throw new InvalidChangeSetException('A set_shown change must have a shown switch that is true or false'),
            ),
            SetTranslated::OP => new SetTranslated(
                self::string($change, 'key'),
                self::string($change, 'field'),
                self::locale($change),
                self::translated($change),
            ),
            SetEntryTranslated::OP => new SetEntryTranslated(
                self::string($change, 'field'),
                self::locale($change),
                self::translated($change),
            ),
            SetLayout::OP => new SetLayout(self::layout($change, $serializer)),
            SetLayoutValue::OP => new SetLayoutValue(
                self::string($change, 'layout'),
                self::string($change, 'field'),
                self::nullableLocale($change),
                array_key_exists('value', $change) ? $change['value'] : throw new InvalidChangeSetException('A set_layout_value change must have a value'),
            ),
            SetLayoutTranslated::OP => new SetLayoutTranslated(
                self::string($change, 'layout'),
                self::string($change, 'field'),
                self::locale($change),
                self::translated($change),
            ),
            default => throw new InvalidChangeSetException(sprintf('A change must have the op %s', implode(', ', self::OPS))),
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
        if (!is_string($from) || !self::isLocaleCode($from) || !is_string($fingerprint) || !Fingerprint::isValid($fingerprint)) {
            throw new InvalidChangeSetException('The "translated" of a change must be null, or have the locale the value was translated from and its fingerprint');
        }

        return ['from' => $from, 'fingerprint' => $fingerprint];
    }

    /**
     * @param array{from: string, fingerprint: string}|null $translated
     */
    public static function source(?array $translated): ?ListedObject
    {
        return null === $translated ? null : new ListedObject(['from' => $translated['from'], 'fingerprint' => $translated['fingerprint']]);
    }

    /**
     * The locale of a change, a locale code such as en_US, as the shop's URLs have it
     *
     * @param array<array-key, mixed> $change
     */
    private static function locale(array $change): string
    {
        $locale = self::string($change, 'locale');
        if (!self::isLocaleCode($locale)) {
            throw new InvalidChangeSetException(sprintf('The locale "%s" of a change must be a locale code, such as en_US', $locale));
        }

        return $locale;
    }

    /**
     * @param array<array-key, mixed> $change
     */
    private static function nullableLocale(array $change): ?string
    {
        return null === ($change['locale'] ?? null) ? null : self::locale($change);
    }

    private static function isLocaleCode(string $value): bool
    {
        return 1 === preg_match(self::LOCALE_CODE, $value);
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

            /** @var array<string, mixed> $map */
            $map = $params;

            return ['kind' => $kind, 'negate' => $negate, 'params' => $map];
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
    private static function layout(array $change, StoredTreeSerializer $serializer): StoredLayout
    {
        try {
            $layout = $serializer->denormalizeLayout($change['layout'] ?? null);
        } catch (InvalidStoredTreeException $e) {
            throw new InvalidChangeSetException(sprintf('The layout of a set_layout change is invalid: %s', $e->getMessage()), previous: $e);
        }

        if (!Code::isValid($layout->code)) {
            throw new InvalidChangeSetException(sprintf('The layout code "%s" of a set_layout change must match %s', $layout->code, Code::PATTERN));
        }

        return $layout;
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
