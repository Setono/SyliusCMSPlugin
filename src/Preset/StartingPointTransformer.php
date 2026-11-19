<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Preset;

use Setono\SyliusCMSPlugin\Registry\LayoutMetadata;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistryInterface;
use Setono\SyliusCMSPlugin\Tree\StoredLayout;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * Builds a new entry's block tree from a starting point. Its blocks are copied as inserting copies them
 * (InsertTransformer). Its layout is used, upgraded, with its field values, from which translation keys resolve and
 * the locales the entry doesn't have are dropped as from blocks. When the code no longer has that layout, or has
 * disabled it, the content type's default layout is used without the values. A block or a layout saved with a newer
 * version than the code's refuses it. See "Starting points" in docs/spec/layouts-and-presets.md
 */
final readonly class StartingPointTransformer
{
    public function __construct(
        private InsertTransformer $insertTransformer,
        private LayoutRegistryInterface $layouts,
        private PresetUpgrader $presetUpgrader,
    ) {
    }

    /**
     * @param list<string> $locales the locales of the new entry
     *
     * @throws \InvalidArgumentException when the preset isn't a starting point
     */
    public function transform(Preset $preset, array $locales): TreeToCreate
    {
        $startingPoint = $preset->startingPoint ?? throw new \InvalidArgumentException('Only a starting point makes a new entry\'s tree');

        $upgraded = $this->presetUpgrader->upgrade($preset);
        $blocks = $this->insertTransformer->transformUpgraded($upgraded, $locales);
        if ($blocks->refused) {
            return self::refused($blocks->notices);
        }

        $stored = $upgraded->tree()->layout;
        $layout = $this->layouts->resolve($startingPoint->contentType, $stored?->code);

        return new TreeToCreate(new StoredTree($this->layout($layout, $stored, $locales), $blocks->blocks), $blocks->notices);
    }

    /**
     * The starting point's layout with its values, or the default layout without them when it's gone or disabled
     *
     * @param LayoutMetadata|null $layout the layout the entry renders in, null for a content type without layouts
     * @param StoredLayout|null $stored the starting point's layout, upgraded
     * @param list<string> $locales
     */
    private function layout(?LayoutMetadata $layout, ?StoredLayout $stored, array $locales): ?StoredLayout
    {
        if (null === $layout) {
            return null;
        }

        if (null === $stored || $stored->code !== $layout->code) {
            return new StoredLayout($layout->code, $layout->version);
        }

        return $this->insertTransformer->transformLayout($stored, $layout, $locales);
    }

    /**
     * @param list<TranslatableMessage> $notices
     */
    private static function refused(array $notices): TreeToCreate
    {
        return new TreeToCreate(new StoredTree(null, []), $notices, true);
    }
}
