<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Problem;

use Psr\Container\ContainerInterface;
use Setono\SyliusCMSPlugin\Field\Reference;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;
use Setono\SyliusCMSPlugin\Rendering\ReferenceLoader;
use Setono\SyliusCMSPlugin\Rendering\ReferenceTypeLoaderInterface;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Sylius\Resource\Model\CodeAwareInterface;

/**
 * What the problem kinds that flag broken references share: which references no longer resolve, and which entries may
 * refer to a record that was deleted or given another code or UUID. Content refers to Sylius's records by their codes
 * and to the CMS's by their UUIDs (ADR 0002). See "References" in docs/spec/content-model.md
 *
 * @internal
 */
final readonly class BrokenReferences
{
    /**
     * @param ContainerInterface $typeLoaders the ReferenceTypeLoaderInterface of each reference type, by type
     */
    public function __construct(
        private ContainerInterface $typeLoaders,
        private EntryRepositoryInterface $entries,
    ) {
    }

    /**
     * The references among these that no longer resolve, as the store no longer has their records, with their keys. Only
     * a reference of a type with a loader is checked, with one query per type
     *
     * @template TKey of array-key
     *
     * @param array<TKey, Reference> $references
     *
     * @return array<TKey, Reference>
     */
    public function broken(array $references): array
    {
        $checked = array_filter($references, fn (Reference $reference): bool => $this->typeLoaders->has($reference->type));

        $existing = [];
        foreach (ReferenceLoader::idsByType($checked) as $type => $ids) {
            $loader = $this->typeLoaders->get($type);
            if ($loader instanceof ReferenceTypeLoaderInterface) {
                $existing[$type] = array_fill_keys($loader->existing($ids), true);
            }
        }

        return array_filter($checked, static fn (Reference $reference): bool => !isset($existing[$reference->type][$reference->id]));
    }

    /**
     * The entries that may refer to one of the records, by its code or UUID now, or by a former one, as they may still and
     * now hold a broken reference
     *
     * @param iterable<object> $records
     * @param list<string> $formerValues the codes or UUIDs they had before
     *
     * @return iterable<EntryInterface>
     */
    public function entriesReferencing(iterable $records, array $formerValues = []): iterable
    {
        $ids = $formerValues;
        foreach ($records as $record) {
            $id = match (true) {
                $record instanceof MediaItemInterface, $record instanceof EntryInterface => $record->getUuid(),
                $record instanceof CodeAwareInterface => $record->getCode(),
                default => null,
            };

            if (null !== $id) {
                $ids[] = $id;
            }
        }

        return [] === $ids ? [] : $this->entries->findReferencing(array_values(array_unique($ids)));
    }
}
