<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Collection;

use Setono\SyliusCMSPlugin\Model\EntryReferenceInterface;

/**
 * How an entry's rows of the reference table change: the (field, UUID) pairs its indexed fields newly hold, which get a
 * row each, and the rows of the pairs they no longer hold, which go. Rows of the pairs they still hold stay
 */
final readonly class EntryReferenceChanges
{
    /**
     * @param list<array{string, string}> $added the (field, UUID) pairs to add a row for
     * @param list<EntryReferenceInterface> $removed the rows to remove
     */
    public function __construct(
        public array $added = [],
        public array $removed = [],
    ) {
    }

    /**
     * @param array<string, list<string>> $held the lowercase UUIDs each indexed field holds, by field name
     * @param iterable<EntryReferenceInterface> $rows the entry's rows as they are
     */
    public static function of(array $held, iterable $rows): self
    {
        $wanted = [];
        foreach ($held as $field => $uuids) {
            foreach ($uuids as $uuid) {
                $wanted[self::key($field, $uuid)] = [$field, $uuid];
            }
        }

        $removed = [];
        foreach ($rows as $row) {
            $key = self::key($row->getField(), $row->getUuid());
            if (isset($wanted[$key])) {
                // Held and written, so it stays, and a second row of the same pair goes
                unset($wanted[$key]);

                continue;
            }

            $removed[] = $row;
        }

        // What's left is held without a row. A pair a row matched was taken out above, so a duplicate row is removed
        return new self(array_values($wanted), $removed);
    }

    public function isEmpty(): bool
    {
        return [] === $this->added && [] === $this->removed;
    }

    private static function key(string $field, string $uuid): string
    {
        return $field . "\0" . $uuid;
    }
}
