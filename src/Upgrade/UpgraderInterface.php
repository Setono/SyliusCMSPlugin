<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Upgrade;

/**
 * One step of a block type's, layout's or content type's upgrade, from the version #[AsUpgrader(from)] names to the
 * next. The plugin sets the new version after it returns. See "Upgraders" in docs/spec/extending.md
 *
 * - For a block type, $data is the block's array in the stored format, without "children"
 * - For a layout, it's the tree's "layout" array
 * - For a content type, it's the entry format without "tree"
 *
 * Its maps (a block's or layout's "fields" and "translated", and the entry's "fields", "translations", and each
 * translation's "fields" and "translated") are arrays. Values inside them are as JSON decodes them, with an empty
 * object as an empty \stdClass
 */
interface UpgraderInterface
{
    /**
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    public function upgrade(array $data): array;
}
