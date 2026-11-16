<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Builder\ChangeSet;

use Setono\SyliusCMSPlugin\Json\ListedObject;
use Setono\SyliusCMSPlugin\Tree\StoredTreeSerializer;

/**
 * Sets where a value of one of the entry's own translatable fields, such as a translation's title, was translated from.
 * Setting it in a locale the entry has no translation in adds the translation. See "Translation state" in
 * docs/spec/content-model.md
 */
final readonly class SetEntryTranslated implements Change
{
    public const OP = 'set_entry_translated';

    /**
     * @param array{from: string, fingerprint: string}|null $translated null when the value has no source
     */
    public function __construct(
        public string $field,
        public string $locale,
        public ?array $translated,
    ) {
    }

    public function toArray(StoredTreeSerializer $serializer): ListedObject
    {
        return new ListedObject([
            'op' => self::OP,
            'field' => $this->field,
            'locale' => $this->locale,
            'translated' => ChangeSet::source($this->translated),
        ]);
    }
}
