<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Publishing;

/**
 * The positions of the entries of a content type ordered by position, once editors drag some of them into a new order
 *
 * @internal
 */
final class EntryPositions
{
    private function __construct()
    {
    }

    /**
     * The entries given take the places they had between them, in the order given, and every entry of the content type
     * is numbered from 0 in the order that gives. Editors order one page of the admin list at a time, so the others keep
     * their places. Entries without a position yet go after those with one
     *
     * @param array<string, int|null> $current every entry of the content type, by UUID, to its position, in the order
     *                                         entries with the same position are in, such as by id
     * @param list<string> $ordered the UUIDs of the entries dragged into order, in their new order
     *
     * @return array<string, int> the UUIDs of the entries whose positions change, to their new positions
     *
     * @throws InvalidEntrySettingsException when an entry given isn't one of the content type's
     */
    public static function reorder(array $current, array $ordered): array
    {
        $ordered = array_values(array_unique($ordered));
        foreach ($ordered as $uuid) {
            if (!array_key_exists($uuid, $current)) {
                throw new InvalidEntrySettingsException(sprintf('The entry %s is not one of the entries to order', $uuid));
            }
        }

        $order = array_keys($current);
        // Stable, so entries with the same position keep the order they're given in
        usort($order, static fn (string $a, string $b): int => [null === $current[$a], $current[$a]] <=> [null === $current[$b], $current[$b]]);

        $places = array_keys(array_intersect($order, $ordered));
        foreach ($places as $index => $place) {
            $order[$place] = $ordered[$index];
        }

        $changed = [];
        foreach ($order as $position => $uuid) {
            if ($current[$uuid] !== $position) {
                $changed[$uuid] = $position;
            }
        }

        return $changed;
    }
}
