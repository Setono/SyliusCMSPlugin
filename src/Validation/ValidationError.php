<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Validation;

/**
 * One thing publish refuses, pointing at where it is: a block's field, a block itself, one of a block's visibility
 * conditions or its parameters, one of the layout's fields, an entry field, or the entry
 */
final readonly class ValidationError implements \JsonSerializable
{
    /** Translated, ready to show */
    public string $message;

    /**
     * @param string|null $block the key of the block it's in, or null when it's in the entry or the layout
     * @param string|null $field the field it's in, or null when it's about the block, the condition or the entry as a
     *                           whole. In a condition, the field is one of its parameters
     * @param string|null $locale the locale of a translatable value, or null
     * @param string|\Stringable $message translated, such as a constraint violation's message
     * @param int|null $condition the index, from 0, of the block's visibility condition it's in, or null when it isn't
     *                            in one
     * @param bool $inLayout whether it's in the tree's layout, in one of its fields or about them together, rather
     *                       than in the entry
     */
    public function __construct(
        public ?string $block,
        public ?string $field,
        public ?string $locale,
        string|\Stringable $message,
        public ?int $condition = null,
        public bool $inLayout = false,
    ) {
        $this->message = (string) $message;
    }

    /**
     * The same error, in the visibility condition at the index of the block with the key, its field one of the
     * condition's parameters
     */
    public function inCondition(string $block, int $index): self
    {
        return new self($block, $this->field, $this->locale, $this->message, condition: $index);
    }

    /**
     * The same error, in one of the fields of the tree's layout
     */
    public function inLayout(): self
    {
        return new self(null, $this->field, $this->locale, $this->message, inLayout: true);
    }

    /**
     * Where it is and its message. "condition" is there only for an error in a visibility condition, and "layout" only
     * for one in the layout's fields
     *
     * @return array{block: string|null, field: string|null, locale: string|null, message: string, condition?: int, layout?: true}
     */
    public function jsonSerialize(): array
    {
        $json = ['block' => $this->block, 'field' => $this->field, 'locale' => $this->locale, 'message' => $this->message];
        if (null !== $this->condition) {
            $json['condition'] = $this->condition;
        }

        if ($this->inLayout) {
            $json['layout'] = true;
        }

        return $json;
    }
}
