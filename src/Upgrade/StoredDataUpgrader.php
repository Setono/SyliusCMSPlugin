<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Upgrade;

use Psr\Container\ContainerInterface;
use Setono\SyliusCMSPlugin\Json\CanonicalJson;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistryInterface;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Setono\SyliusCMSPlugin\Tree\StoredTreeSerializer;

/**
 * The upgrade service. See "Schema changes" in docs/spec/content-model.md and "Upgraders" in docs/spec/extending.md
 */
final readonly class StoredDataUpgrader implements StoredDataUpgraderInterface
{
    /** The maps of a block and of a layout */
    private const MAPS = ['fields', 'translated'];

    /**
     * @param ContainerInterface $upgraders the upgraders by Kind::upgraderId()
     */
    public function __construct(
        private BlockTypeRegistryInterface $blockTypes,
        private LayoutRegistryInterface $layouts,
        private ContentTypeRegistryInterface $contentTypes,
        private ContainerInterface $upgraders,
    ) {
    }

    /**
     * @param array<array-key, mixed> $entry
     */
    public function upgradeEntry(array $entry): Upgraded
    {
        $tree = $entry['tree'] ?? null;
        unset($entry['tree']);

        $newer = [];
        $contentType = $entry['contentType'] ?? null;
        if (is_string($contentType) && $this->contentTypes->has($contentType)) {
            $upgraded = $this->steps(Kind::ContentType, $contentType, $contentType, $entry, $this->contentTypes->get($contentType)->version);
            [$entry, $newer] = [$upgraded->data, $upgraded->newer];
        }

        if (is_array($tree) && is_string($contentType)) {
            $upgraded = $this->upgradeTree($tree, $contentType);
            [$tree, $newer] = [$upgraded->data, [...$newer, ...$upgraded->newer]];
        }

        if (null !== $tree) {
            $entry['tree'] = $tree;
        }

        return new Upgraded($entry, $newer);
    }

    /**
     * @param array<array-key, mixed> $tree
     */
    public function upgradeTree(array $tree, string $contentType): Upgraded
    {
        $newer = [];
        $layout = $tree['layout'] ?? null;
        if (is_array($layout) && is_string($layout['code'] ?? null) && $this->layouts->has($contentType, $layout['code'])) {
            $metadata = $this->layouts->get($contentType, $layout['code']);
            $upgraded = $this->steps(Kind::Layout, $contentType . ':' . $metadata->code, $metadata->code, $layout, $metadata->version, array_keys($metadata->fields));
            [$tree['layout'], $newer] = [$upgraded->data, $upgraded->newer];
        }

        if (is_array($tree['blocks'] ?? null)) {
            $upgraded = $this->upgradeBlocks($tree['blocks']);
            [$tree['blocks'], $newer] = [$upgraded->data, [...$newer, ...$upgraded->newer]];
        }

        return new Upgraded($tree, $newer);
    }

    public function readBlockTree(EntryInterface $entry): StoredTree
    {
        $serializer = new StoredTreeSerializer();

        // An entry's tree is valid JSON, because only StoredTreeSerializer writes it
        $tree = CanonicalJson::decode($entry->getBlockTreeJson());
        if (is_array($tree)) {
            $tree = $this->upgradeTree($tree, $this->contentTypes->getByEntry($entry)->code)->data;
        }

        return $serializer->denormalize($tree);
    }

    /**
     * @param array<array-key, mixed> $blocks
     */
    public function upgradeBlocks(array $blocks): Upgraded
    {
        $newer = [];
        foreach ($blocks as $index => $block) {
            // What isn't a block is left for StoredTreeSerializer to refuse
            if (is_array($block)) {
                $upgraded = $this->upgradeBlock($block);
                [$blocks[$index], $newer] = [$upgraded->data, [...$newer, ...$upgraded->newer]];
            }
        }

        return new Upgraded($blocks, $newer);
    }

    /**
     * A block's upgrader gets it without its children, which are upgraded on their own
     *
     * @param array<array-key, mixed> $block
     */
    private function upgradeBlock(array $block): Upgraded
    {
        $children = $block['children'] ?? null;
        unset($block['children']);

        $newer = [];
        $type = $block['type'] ?? null;
        if (is_string($type) && $this->blockTypes->has($type)) {
            $metadata = $this->blockTypes->get($type);
            $upgraded = $this->steps(Kind::BlockType, $type, $type, $block, $metadata->version, array_keys($metadata->fields));
            [$block, $newer] = [$upgraded->data, $upgraded->newer];
        }

        if (is_array($children)) {
            $upgraded = $this->upgradeBlocks($children);
            [$children, $newer] = [$upgraded->data, [...$newer, ...$upgraded->newer]];
        }

        if (null !== $children) {
            $block['children'] = $children;
        }

        return new Upgraded($block, $newer);
    }

    /**
     * Runs the upgraders from the data's version up to $target, setting the version after each, and then drops the
     * values and translation state of removed fields. The upgraders get the data's maps as arrays, and the result has
     * them back as CanonicalJson::decode() gives them. Data saved with a newer version than $target is kept as it is
     *
     * @param string $subject the code the upgraders are registered under: Kind::upgraderId()
     * @param array<array-key, mixed> $data
     * @param list<string>|null $fields the names of the fields the code has, or null to keep every stored value
     */
    private function steps(Kind $kind, string $subject, string $code, array $data, int $target, ?array $fields = null): Upgraded
    {
        $version = $data['version'] ?? null;
        if (!is_int($version)) {
            return new Upgraded($data);
        }

        if ($version > $target) {
            return new Upgraded($data, [new NewerVersion($kind, $code, $version, $target)]);
        }

        $data = self::maps($kind, $data, self::toArray(...));

        while ($version < $target) {
            $id = $kind->upgraderId($subject, $version);
            if ($this->upgraders->has($id)) {
                /** @var UpgraderInterface $upgrader */
                $upgrader = $this->upgraders->get($id);
                $data = $upgrader->upgrade($data);
            }

            $data['version'] = ++$version;
        }

        if (null !== $fields) {
            // A removed field's stored value is ignored, and dropped on the next save
            $data = self::convert($data, self::MAPS, static fn (mixed $map): mixed => is_array($map) ? array_intersect_key($map, array_flip($fields)) : $map);
        }

        return new Upgraded(self::maps($kind, $data, self::toObject(...)));
    }

    /**
     * Applies $convert to the maps of a block or layout ("fields" and "translated"), or of an entry without its tree
     * ("fields", "translations", and each translation's "fields" and "translated")
     *
     * @param array<array-key, mixed> $data
     * @param callable(mixed): mixed $convert
     *
     * @return array<array-key, mixed>
     */
    private static function maps(Kind $kind, array $data, callable $convert): array
    {
        if (Kind::ContentType !== $kind) {
            return self::convert($data, self::MAPS, $convert);
        }

        $data = self::convert($data, ['fields', 'translations'], $convert);
        if (is_array($data['translations'] ?? null)) {
            $data['translations'] = array_map(static fn (mixed $translation): mixed => is_array($translation) ? self::convert($translation, self::MAPS, $convert) : $translation, $data['translations']);
        }

        return $data;
    }

    /**
     * @param array<array-key, mixed> $data
     * @param list<string> $keys
     * @param callable(mixed): mixed $convert
     *
     * @return array<array-key, mixed>
     */
    private static function convert(array $data, array $keys, callable $convert): array
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                $data[$key] = $convert($data[$key]);
            }
        }

        return $data;
    }

    private static function toArray(mixed $map): mixed
    {
        return $map instanceof \stdClass ? get_object_vars($map) : $map;
    }

    private static function toObject(mixed $map): mixed
    {
        return [] === $map ? new \stdClass() : $map;
    }
}
