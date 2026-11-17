<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Rendering;

use Psr\Container\ContainerInterface;
use Setono\SyliusCMSPlugin\Field\Reference;

/**
 * Groups references by type and loads each type's together, each id once, through the type's loader: one query per
 * type. What a type's loader doesn't return is left out of the map, so it renders as a broken reference. See "Rendering
 * a block tree" in docs/spec/rendering.md
 */
final readonly class ReferenceLoader implements ReferenceLoaderInterface
{
    /**
     * @param ContainerInterface $typeLoaders the ReferenceTypeLoaderInterface of each reference type, by type
     */
    public function __construct(private ContainerInterface $typeLoaders)
    {
    }

    public function load(iterable $references, RenderContext $context): ReferenceMap
    {
        $records = [];
        foreach (self::idsByType($references) as $type => $typeIds) {
            $loader = $this->typeLoaders->has($type) ? $this->typeLoaders->get($type) : null;
            if (!$loader instanceof ReferenceTypeLoaderInterface) {
                continue;
            }

            $records[$type] = array_intersect_key($loader->load($typeIds, $context), array_flip($typeIds));
        }

        return new ReferenceMap($records);
    }

    /**
     * The ids of the references by type, each once
     *
     * @param iterable<Reference> $references
     *
     * @return array<string, non-empty-list<string>>
     */
    public static function idsByType(iterable $references): array
    {
        $ids = [];
        foreach ($references as $reference) {
            $ids[$reference->type][$reference->id] = true;
        }

        return array_map(static fn (array $typeIds): array => array_map(strval(...), array_keys($typeIds)), $ids);
    }
}
