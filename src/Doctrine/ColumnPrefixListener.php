<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Doctrine;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Setono\SyliusCMSPlugin\Model\Entry;
use Setono\SyliusCMSPlugin\Model\EntryTranslation;

/**
 * Every content type shares the entry table, and its translations the translation table (ADR 0001), so the plugin
 * prefixes the columns of each subclass, and of its translation subclass, with the content type's code and "_", such as
 * blog_post_published_at. Projects map the columns of their content types' fields as usual, and never prefix them
 * themselves. It prefixes the columns of the fields and the to-one join columns the model declares, and those of the
 * mapped superclasses between it and the root, such as a shipped model a project subclasses, but not the root's, which
 * every content type shares. See "Content types" in docs/spec/extending.md
 */
final readonly class ColumnPrefixListener
{
    /** @var array<class-string, string> the models and translation models of the content types, to their codes */
    private array $codes;

    /**
     * @param array<string, array{model: class-string, translation: class-string|null, resource: string}> $contentTypes content type code => its resource
     */
    public function __construct(array $contentTypes)
    {
        $codes = [];
        foreach ($contentTypes as $code => $resource) {
            $codes[$resource['model']] ??= $code;

            // A content type without translatable fields of its own uses the root, which no content type owns
            if (null !== $resource['translation']) {
                $codes[$resource['translation']] ??= $code;
            }
        }

        unset($codes[Entry::class], $codes[EntryTranslation::class]);
        $this->codes = $codes;
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $event): void
    {
        /** @var ClassMetadata<object> $metadata */
        $metadata = $event->getClassMetadata();

        $code = $this->codes[$metadata->getName()] ?? null;
        if (null === $code) {
            return;
        }

        $prefix = $code . '_';

        foreach ($metadata->fieldMappings as $field => $mapping) {
            // An entity it inherits from, the root or another content type, has its own columns already
            if (isset($mapping['inherited'])) {
                continue;
            }

            $column = $mapping['columnName'];
            $prefixed = $prefix . $column;

            $metadata->fieldMappings[$field]['columnName'] = $prefixed;
            $metadata->columnNames[$field] = $prefixed;
            unset($metadata->fieldNames[$column]);
            $metadata->fieldNames[$prefixed] = $field;
        }

        $mappings = [];
        foreach ($metadata->associationMappings as $field => $mapping) {
            $mappings[$field] = isset($mapping['inherited']) || !isset($mapping['joinColumns']) ? $mapping : self::prefixJoinColumns($mapping, $prefix);
        }

        // Written whole, as Doctrine's mapping types differ between its versions
        (new \ReflectionProperty($metadata, 'associationMappings'))->setValue($metadata, $mappings);
    }

    /**
     * @param array<string, mixed> $mapping a to-one association that owns its join columns
     *
     * @return array<string, mixed>
     */
    private static function prefixJoinColumns(array $mapping, string $prefix): array
    {
        $prefixed = static fn (string $column): string => $prefix . $column;

        /** @var list<array{name: string}> $joinColumns */
        $joinColumns = $mapping['joinColumns'];
        foreach ($joinColumns as $index => $joinColumn) {
            $joinColumns[$index]['name'] = $prefixed($joinColumn['name']);
        }
        $mapping['joinColumns'] = $joinColumns;

        /** @var array<string, string> $sourceToTarget */
        $sourceToTarget = $mapping['sourceToTargetKeyColumns'] ?? [];
        $mapping['sourceToTargetKeyColumns'] = array_combine(array_map($prefixed, array_keys($sourceToTarget)), array_values($sourceToTarget));

        /** @var array<string, string> $targetToSource */
        $targetToSource = $mapping['targetToSourceKeyColumns'] ?? [];
        $mapping['targetToSourceKeyColumns'] = array_map($prefixed, $targetToSource);

        /** @var array<string, string> $joinColumnFieldNames */
        $joinColumnFieldNames = $mapping['joinColumnFieldNames'] ?? [];
        $mapping['joinColumnFieldNames'] = array_combine(array_map($prefixed, array_keys($joinColumnFieldNames)), array_map($prefixed, array_values($joinColumnFieldNames)));

        return $mapping;
    }
}
