<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer\Import;

use Psr\Container\ContainerInterface;
use Setono\SyliusCMSPlugin\Binding\StoredBinding;
use Setono\SyliusCMSPlugin\Condition\ConditionMatcher;
use Setono\SyliusCMSPlugin\Field\Reference;
use Setono\SyliusCMSPlugin\Field\ReferenceType;
use Setono\SyliusCMSPlugin\Rendering\ReferenceTypeLoaderInterface;
use Setono\SyliusCMSPlugin\Targeting\CustomerGroupValue;
use Setono\SyliusCMSPlugin\Tree\ReferenceFinder;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Customer\Model\CustomerGroupInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Component\Product\Model\ProductAttributeInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;

/**
 * The references the records an import writes hold that don't resolve in the target: an entry or a media item that's
 * neither in the export nor in the target, a product or a taxon the target lacks, a product attribute a binding shows,
 * and the channels, customer groups and locales of their settings and translations. They're found in fields, as the
 * admin's broken references are, in bindings, in blocks' visibility conditions and in placements' page scopes. They
 * import unchanged. See "References that don't resolve" in docs/spec/transfer.md
 *
 * @internal
 */
final readonly class UnresolvedReferences
{
    /** The shipped Product attribute binding, whose attribute parameter is a product attribute's code */
    private const PRODUCT_ATTRIBUTE_BINDING = 'product.attribute';

    /**
     * @param ContainerInterface $typeLoaders the ReferenceTypeLoaderInterface of each reference type, by type
     * @param ChannelRepositoryInterface<ChannelInterface> $channels
     * @param RepositoryInterface<CustomerGroupInterface> $customerGroups
     * @param RepositoryInterface<LocaleInterface> $locales
     * @param RepositoryInterface<ProductAttributeInterface> $productAttributes
     */
    public function __construct(
        private ReferenceFinder $referenceFinder,
        private ConditionMatcher $conditionMatcher,
        private ContainerInterface $typeLoaders,
        private ChannelRepositoryInterface $channels,
        private RepositoryInterface $customerGroups,
        private RepositoryInterface $locales,
        private RepositoryInterface $productAttributes,
    ) {
    }

    /**
     * @param list<ReadEntry> $entries the entries the import writes
     * @param list<ReadPreset> $presets the presets the import writes
     *
     * @return list<UnresolvedReference> by record, in the order of the records
     */
    public function in(ReadExport $export, array $entries, array $presets): array
    {
        /** @var list<array{string, string, string, string}> $held each record's UUID and label, and a type and id */
        $held = [];
        foreach ($entries as $entry) {
            foreach ($this->entryReferences($entry) as $reference) {
                $held[] = [$entry->uuid(), $entry->label(), $reference->type, $reference->id];
            }

            $settings = $entry->exported->settings;
            foreach ([...$settings->channels, ...$settings->homepageOf] as $code) {
                $held[] = [$entry->uuid(), $entry->label(), UnresolvedReference::CHANNEL, $code];
            }

            foreach ($settings->audience->groups as $code) {
                if (!CustomerGroupValue::isReserved($code)) {
                    $held[] = [$entry->uuid(), $entry->label(), UnresolvedReference::CUSTOMER_GROUP, $code];
                }
            }

            foreach (self::boundAttributes($entry->content->getBlockTree()) as $code) {
                $held[] = [$entry->uuid(), $entry->label(), UnresolvedReference::PRODUCT_ATTRIBUTE, $code];
            }

            foreach ($entry->content->getTranslations()->getKeys() as $localeCode) {
                $held[] = [$entry->uuid(), $entry->label(), UnresolvedReference::LOCALE, (string) $localeCode];
            }
        }

        foreach ($presets as $preset) {
            $startingPoint = $preset->preset->startingPoint;
            $found = null === $startingPoint ? $this->referenceFinder->inBlocks($preset->preset->blocks) : $this->referenceFinder->inTree(new StoredTree($startingPoint->layout, $preset->preset->blocks), $startingPoint->contentType);
            foreach (ReferenceFinder::references($found) as $reference) {
                $held[] = [$preset->uuid(), $preset->label(), $reference->type, $reference->id];
            }

            foreach (self::boundAttributes(new StoredTree($startingPoint?->layout, $preset->preset->blocks)) as $code) {
                $held[] = [$preset->uuid(), $preset->label(), UnresolvedReference::PRODUCT_ATTRIBUTE, $code];
            }
        }

        $resolved = $this->resolved($export, $held);

        $unresolved = [];
        foreach ($held as [$uuid, $label, $type, $id]) {
            $key = $uuid . "\0" . $type . "\0" . $id;
            if (isset($resolved[$type][$id]) || isset($unresolved[$key])) {
                continue;
            }

            $unresolved[$key] = new UnresolvedReference($uuid, $label, $type, $id);
        }

        return array_values($unresolved);
    }

    /**
     * The references in its fields and its tree, and in the visibility conditions of its blocks and the page scopes of a
     * snippet's placements
     *
     * @return list<Reference>
     */
    private function entryReferences(ReadEntry $entry): array
    {
        $references = iterator_to_array(ReferenceFinder::references($this->referenceFinder->inEntry($entry->content, $entry->contentType)), false);
        foreach ($entry->content->getBlockTree()->allBlocks() as $block) {
            array_push($references, ...$this->conditionMatcher->references($block->conditions));
        }

        foreach ($entry->exported->placements as $placement) {
            array_push($references, ...$this->conditionMatcher->references($placement->scope));
        }

        return $references;
    }

    /**
     * The ids of each type that resolve: an entry's or media item's in the export or the target, and every other
     * that the target has. A reference type without a loader, which nothing can check, counts as resolved
     *
     * @param list<array{string, string, string, string}> $held
     *
     * @return array<string, array<string, true>> type => id => true
     */
    private function resolved(ReadExport $export, array $held): array
    {
        $ids = [];
        foreach ($held as [, , $type, $id]) {
            $ids[$type][$id] = true;
        }

        $resolved = [
            ReferenceType::ENTRY => array_fill_keys(array_keys($export->entries), true),
            ReferenceType::MEDIA_ITEM => array_fill_keys(array_keys($export->mediaItems), true),
        ];

        foreach ($ids as $type => $byId) {
            $existing = match ($type) {
                UnresolvedReference::CHANNEL => self::codes($this->channels->findBy(['code' => array_keys($byId)])),
                UnresolvedReference::CUSTOMER_GROUP => self::codes($this->customerGroups->findBy(['code' => array_keys($byId)])),
                UnresolvedReference::LOCALE => self::codes($this->locales->findBy(['code' => array_keys($byId)])),
                UnresolvedReference::PRODUCT_ATTRIBUTE => self::codes($this->productAttributes->findBy(['code' => array_keys($byId)])),
                default => $this->existing((string) $type, array_map(strval(...), array_keys($byId))),
            };

            foreach ($existing as $id) {
                $resolved[$type][$id] = true;
            }
        }

        return $resolved;
    }

    /**
     * @param non-empty-list<string> $ids
     *
     * @return list<string>
     */
    private function existing(string $type, array $ids): array
    {
        if (!$this->typeLoaders->has($type)) {
            return $ids;
        }

        $loader = $this->typeLoaders->get($type);

        return $loader instanceof ReferenceTypeLoaderInterface ? $loader->existing($ids) : $ids;
    }

    /**
     * The codes of the product attributes the tree's bindings show: in its layout's and blocks' values, a translatable
     * one's in each locale
     *
     * @return list<string>
     */
    private static function boundAttributes(StoredTree $tree): array
    {
        $codes = [];
        $walk = static function (mixed $value) use (&$walk, &$codes): void {
            $binding = StoredBinding::fromValue($value);
            if (null !== $binding) {
                $attribute = $binding->params['attribute'] ?? null;
                if (self::PRODUCT_ATTRIBUTE_BINDING === $binding->property && is_string($attribute) && '' !== $attribute) {
                    $codes[$attribute] = $attribute;
                }

                return;
            }

            if (is_array($value) || $value instanceof \stdClass) {
                foreach ((array) $value as $inner) {
                    $walk($inner);
                }
            }
        };

        $walk($tree->layout->fields ?? []);
        foreach ($tree->allBlocks() as $block) {
            $walk($block->fields);
        }

        return array_values($codes);
    }

    /**
     * @param iterable<ChannelInterface|CustomerGroupInterface|LocaleInterface|ProductAttributeInterface> $records
     *
     * @return list<string>
     */
    private static function codes(iterable $records): array
    {
        $codes = [];
        foreach ($records as $record) {
            $code = $record->getCode();
            if (null !== $code) {
                $codes[] = $code;
            }
        }

        return $codes;
    }
}
