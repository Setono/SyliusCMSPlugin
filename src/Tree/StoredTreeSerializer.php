<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tree;

use Setono\SyliusCMSPlugin\Json\CanonicalJson;
use Setono\SyliusCMSPlugin\Json\ListedObject;

/**
 * Reads and writes the stored block tree format (docs/spec/content-model.md), which is a contract:
 * every key is always present, listed keys keep the spec's order and everything is written as canonical JSON
 */
final class StoredTreeSerializer
{
    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';

    private const CODE_PATTERN = '/^[a-z][a-z0-9_]*$/';

    public function toJson(StoredTree $tree): string
    {
        return CanonicalJson::encode($this->normalize($tree));
    }

    /**
     * @throws InvalidStoredTreeException
     */
    public function fromJson(string $json): StoredTree
    {
        try {
            $data = CanonicalJson::decode($json);
        } catch (\JsonException $e) {
            throw new InvalidStoredTreeException(sprintf('The block tree is not valid JSON: %s', $e->getMessage()), previous: $e);
        }

        return $this->denormalize($data);
    }

    public function normalize(StoredTree $tree): ListedObject
    {
        return new ListedObject([
            'layout' => null === $tree->layout ? null : new ListedObject([
                'code' => $tree->layout->code,
                'version' => $tree->layout->version,
                'fields' => (object) $tree->layout->fields,
                'translated' => $this->normalizeTranslated($tree->layout->translated),
            ]),
            'blocks' => array_map($this->normalizeBlock(...), $tree->blocks),
        ]);
    }

    private function normalizeBlock(StoredBlock $block): ListedObject
    {
        return new ListedObject([
            'key' => $block->key,
            'type' => $block->type,
            'version' => $block->version,
            'style' => $block->style,
            'fields' => (object) $block->fields,
            'translated' => $this->normalizeTranslated($block->translated),
            'conditions' => array_map(static fn (array $condition): ListedObject => new ListedObject([
                'kind' => $condition['kind'] ?? null,
                'negate' => $condition['negate'] ?? false,
                'params' => (object) ($condition['params'] ?? []),
            ]), $block->conditions),
            'children' => array_map($this->normalizeBlock(...), $block->children),
        ]);
    }

    /**
     * @throws InvalidStoredTreeException
     */
    private function denormalize(mixed $data): StoredTree
    {
        $data = self::map($data, 'The block tree');

        $layout = $data['layout'] ?? null;
        $blocks = $data['blocks'] ?? null;
        if (!is_array($blocks) || !array_is_list($blocks)) {
            throw new InvalidStoredTreeException('The block tree must have a list of blocks');
        }

        return new StoredTree(
            null === $layout ? null : $this->denormalizeLayout($layout),
            array_map($this->denormalizeBlock(...), $blocks),
        );
    }

    /**
     * @throws InvalidStoredTreeException
     */
    public function denormalizeBlock(mixed $data): StoredBlock
    {
        $data = self::map($data, 'A block');

        $key = $data['key'] ?? null;
        if (!is_string($key) || 1 !== preg_match(self::UUID_PATTERN, $key)) {
            throw new InvalidStoredTreeException('A block must have a lowercase UUID as its key');
        }

        $type = $data['type'] ?? null;
        if (!is_string($type) || 1 !== preg_match(self::CODE_PATTERN, $type)) {
            throw new InvalidStoredTreeException(sprintf('The block "%s" must have a block type code as its type', $key));
        }

        $style = $data['style'] ?? null;
        if (null !== $style && !is_string($style)) {
            throw new InvalidStoredTreeException(sprintf('The style of the block "%s" must be a string or null', $key));
        }

        $conditions = self::list($data['conditions'] ?? [], sprintf('The conditions of the block "%s"', $key));
        $children = self::list($data['children'] ?? [], sprintf('The children of the block "%s"', $key));

        return new StoredBlock(
            $key,
            $type,
            self::version($data['version'] ?? null, sprintf('The block "%s"', $key)),
            $style,
            self::map($data['fields'] ?? [], sprintf('The fields of the block "%s"', $key)),
            self::map($data['translated'] ?? [], sprintf('The translation state of the block "%s"', $key)),
            array_map(static fn (mixed $condition): array => self::map($condition, sprintf('A condition of the block "%s"', $key)), $conditions),
            array_map($this->denormalizeBlock(...), $children),
        );
    }

    /**
     * @throws InvalidStoredTreeException
     */
    private function denormalizeLayout(mixed $data): StoredLayout
    {
        $data = self::map($data, 'The layout');

        $code = $data['code'] ?? null;
        if (!is_string($code)) {
            throw new InvalidStoredTreeException('The layout must have a code');
        }

        return new StoredLayout(
            $code,
            self::version($data['version'] ?? null, 'The layout'),
            self::map($data['fields'] ?? [], 'The fields of the layout'),
            self::map($data['translated'] ?? [], 'The translation state of the layout'),
        );
    }

    /**
     * @param array<string, mixed> $translated field name => locale code => translation state
     */
    private function normalizeTranslated(array $translated): \stdClass
    {
        return (object) array_map(
            static fn (mixed $locales): \stdClass => (object) array_map(
                static fn (mixed $state): ListedObject => new ListedObject([
                    'from' => is_array($state) ? ($state['from'] ?? null) : null,
                    'fingerprint' => is_array($state) ? ($state['fingerprint'] ?? null) : null,
                ]),
                is_array($locales) ? $locales : [],
            ),
            $translated,
        );
    }

    /**
     * A JSON object, decoded as an array that isn't a list, or as an empty stdClass when it's empty
     *
     * @return array<string, mixed>
     *
     * @throws InvalidStoredTreeException
     */
    private static function map(mixed $data, string $what): array
    {
        if ($data instanceof \stdClass) {
            $data = get_object_vars($data);
        }

        if (!is_array($data) || ([] !== $data && array_is_list($data))) {
            throw new InvalidStoredTreeException(sprintf('%s must be a JSON object', $what));
        }

        /** @var array<string, mixed> $map */
        $map = $data;

        return $map;
    }

    /**
     * @return list<mixed>
     *
     * @throws InvalidStoredTreeException
     */
    private static function list(mixed $data, string $what): array
    {
        if (!is_array($data) || !array_is_list($data)) {
            throw new InvalidStoredTreeException(sprintf('%s must be a JSON array', $what));
        }

        return $data;
    }

    /**
     * @throws InvalidStoredTreeException
     */
    private static function version(mixed $version, string $what): int
    {
        if (!is_int($version) || $version < 1) {
            throw new InvalidStoredTreeException(sprintf('%s must have a version of 1 or more', $what));
        }

        return $version;
    }
}
