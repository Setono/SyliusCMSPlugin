<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Builder\ChangeSet;

use Setono\SyliusCMSPlugin\Json\ListedObject;
use Setono\SyliusCMSPlugin\Tree\StoredTreeSerializer;

/**
 * Sets where a block's translatable value in one locale was translated from: the locale and the fingerprint of the
 * value it was translated from, as "Copy it here", typing over a fallback value and "Mark as up to date" record it.
 * See "Translation state" in docs/spec/content-model.md
 */
final readonly class SetTranslated implements Change
{
    public const OP = 'set_translated';

    /**
     * @param string $key the block's key
     * @param array{from: string, fingerprint: string}|null $translated null when the value has no source
     */
    public function __construct(
        public string $key,
        public string $field,
        public string $locale,
        public ?array $translated,
    ) {
    }

    public function toArray(StoredTreeSerializer $serializer): ListedObject
    {
        return new ListedObject([
            'op' => self::OP,
            'key' => $this->key,
            'field' => $this->field,
            'locale' => $this->locale,
            'translated' => ChangeSet::source($this->translated),
        ]);
    }
}
