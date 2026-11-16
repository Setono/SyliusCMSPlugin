<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Builder\ChangeSet;

use Setono\SyliusCMSPlugin\Json\ListedObject;
use Setono\SyliusCMSPlugin\Tree\StoredTreeSerializer;

/**
 * Sets where a layout field's translatable value in one locale was translated from, as SetTranslated does for a block's.
 * See "Translation state" in docs/spec/content-model.md
 */
final readonly class SetLayoutTranslated implements Change
{
    public const OP = 'set_layout_translated';

    /**
     * @param string $layout the code of the layout the value is in
     * @param array{from: string, fingerprint: string}|null $translated null when the value has no source
     */
    public function __construct(
        public string $layout,
        public string $field,
        public string $locale,
        public ?array $translated,
    ) {
    }

    public function toArray(StoredTreeSerializer $serializer): ListedObject
    {
        return new ListedObject([
            'op' => self::OP,
            'layout' => $this->layout,
            'field' => $this->field,
            'locale' => $this->locale,
            'translated' => ChangeSet::source($this->translated),
        ]);
    }
}
