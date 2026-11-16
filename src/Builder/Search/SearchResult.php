<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Builder\Search;

/**
 * One thing a picker's search found: what a field stores to refer to it, its name and its thumbnail.
 * See "Widgets" in docs/spec/extending.md
 */
final readonly class SearchResult implements \JsonSerializable
{
    /**
     * @param string $ref a code or a UUID
     * @param string|null $thumbnail a URL, or null when it has none
     */
    public function __construct(
        public string $ref,
        public string $name,
        public ?string $thumbnail = null,
    ) {
    }

    /**
     * @return array{ref: string, name: string, thumbnail: string|null}
     */
    public function jsonSerialize(): array
    {
        return ['ref' => $this->ref, 'name' => $this->name, 'thumbnail' => $this->thumbnail];
    }
}
