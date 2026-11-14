<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Validation;

/**
 * One thing publish refuses, pointing at where it is: a block's field, a block itself, an entry field, or the entry
 */
final readonly class ValidationError implements \JsonSerializable
{
    /** Translated, ready to show */
    public string $message;

    /**
     * @param string|null $block the key of the block it's in, or null when it's in the entry
     * @param string|null $field the field it's in, or null when it's about the block or the entry as a whole
     * @param string|null $locale the locale of a translatable value, or null
     * @param string|\Stringable $message translated, such as a constraint violation's message
     */
    public function __construct(
        public ?string $block,
        public ?string $field,
        public ?string $locale,
        string|\Stringable $message,
    ) {
        $this->message = (string) $message;
    }

    /**
     * @return array{block: string|null, field: string|null, locale: string|null, message: string}
     */
    public function jsonSerialize(): array
    {
        return ['block' => $this->block, 'field' => $this->field, 'locale' => $this->locale, 'message' => $this->message];
    }
}
