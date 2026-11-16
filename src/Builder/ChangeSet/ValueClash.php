<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Builder\ChangeSet;

/**
 * The editor and a colleague both changed the same field in the same locale
 */
final readonly class ValueClash implements Clash
{
    /**
     * @param string|null $locale null for a shared field
     * @param mixed $mine the editor's value, which isn't applied; null when they removed it
     * @param mixed $theirs the value the working copy holds now; null when it has none
     */
    public function __construct(
        public string $key,
        public string $field,
        public ?string $locale,
        public mixed $mine,
        public mixed $theirs,
    ) {
    }

    public function toArray(): array
    {
        return [
            'kind' => 'value',
            'key' => $this->key,
            'field' => $this->field,
            'locale' => $this->locale,
            'mine' => $this->mine,
            'theirs' => $this->theirs,
        ];
    }
}
