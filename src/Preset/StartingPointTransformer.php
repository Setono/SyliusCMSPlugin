<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Preset;

use Setono\SyliusCMSPlugin\Json\CanonicalJson;
use Setono\SyliusCMSPlugin\Registry\LayoutMetadata;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistryInterface;
use Setono\SyliusCMSPlugin\Tree\StoredLayout;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Setono\SyliusCMSPlugin\Tree\StoredTreeSerializer;
use Setono\SyliusCMSPlugin\Upgrade\StoredDataUpgraderInterface;
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
        private StoredDataUpgraderInterface $upgrader,
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

        $layout = $this->layouts->resolve($startingPoint->contentType, $startingPoint->layout?->code);
        $stored = $startingPoint->layout;
        if (null !== $layout && null !== $stored && $stored->code === $layout->code) {
            $upgraded = $this->upgrader->upgradeTree([
                'layout' => CanonicalJson::decode(CanonicalJson::encode((new StoredTreeSerializer())->normalizeLayout($stored))),
                'blocks' => [],
            ], $startingPoint->contentType);
            if ([] !== $upgraded->newer) {
                return self::refused(array_map(InsertTransformer::newerVersionNotice(...), $upgraded->newer));
            }

            $stored = (new StoredTreeSerializer())->denormalizeLayout($upgraded->data['layout'] ?? null);
        }

        $blocks = $this->insertTransformer->transform($preset, $locales);
        if ($blocks->refused) {
            return self::refused($blocks->notices);
        }

        return new TreeToCreate(new StoredTree($this->layout($layout, $stored, $locales), $blocks->blocks), $blocks->notices);
    }

    /**
     * The starting point's layout with its values, or the default layout without them when it's gone or disabled
     *
     * @param LayoutMetadata|null $layout the layout the entry renders in, null for a content type without layouts
     * @param StoredLayout|null $stored the starting point's layout, upgraded when the code has it
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
