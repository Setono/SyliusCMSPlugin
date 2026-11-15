<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Registry;

use Setono\SyliusCMSPlugin\Attribute\FieldType;
use Setono\SyliusCMSPlugin\Field\Field;

/**
 * Reads the fields of a class: its typed properties that carry a field attribute
 */
final class FieldReader
{
    /**
     * The fields of a block type or a layout: its public properties with a field attribute
     *
     * @param class-string $class
     *
     * @return array<string, FieldMetadata> property name => field, in declaration order
     */
    public function read(string $class): array
    {
        return self::fields($class, \ReflectionProperty::IS_PUBLIC);
    }

    /**
     * The fields of a content type's entry or translation class: its Doctrine-mapped properties with a field attribute,
     * which can be private. Translatability follows where the property sits: it's shared on the entry class and
     * translatable on the translation class. See "Fields" in docs/spec/content-model.md
     *
     * @param class-string $class
     *
     * @return array<string, FieldMetadata> property name => field, in declaration order
     */
    public function readContentType(string $class, bool $translation): array
    {
        return self::fields($class, null, $translation);
    }

    /**
     * @param class-string $class
     * @param int|null $visibility a \ReflectionProperty::IS_* filter, or null for every property
     * @param bool|null $translatable what every field is, or null to resolve it from the field or else the field type
     *
     * @return array<string, FieldMetadata>
     */
    private static function fields(string $class, ?int $visibility, ?bool $translatable = null): array
    {
        $fields = [];

        foreach ((new \ReflectionClass($class))->getProperties($visibility) as $property) {
            if ($property->isStatic()) {
                continue;
            }

            foreach ($property->getAttributes(Field::class, \ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
                $field = $attribute->newInstance();
                $type = self::fieldType($field);

                $fields[$property->getName()] = new FieldMetadata(
                    $property->getName(),
                    $field,
                    $type,
                    $translatable ?? $field->translatable ?? $type->translatable,
                    $property->getDefaultValue(),
                );
            }
        }

        return $fields;
    }

    private static function fieldType(Field $field): FieldType
    {
        for ($class = new \ReflectionClass($field); false !== $class; $class = $class->getParentClass()) {
            foreach ($class->getAttributes(FieldType::class) as $attribute) {
                return $attribute->newInstance();
            }
        }

        throw new \LogicException(sprintf('The field attribute %s must carry #[FieldType]', $field::class));
    }
}
