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
        return self::fields((new \ReflectionClass($class))->getProperties(\ReflectionProperty::IS_PUBLIC));
    }

    /**
     * The fields of a content type's entry or translation class: its Doctrine-mapped properties with a field attribute,
     * which can be private. Translatability follows where the property sits: it's shared on the entry class and
     * translatable on the translation class. A subclass, such as a project's of a shipped model, has the fields of the
     * classes it extends, private ones included, before its own. See "Fields" in docs/spec/content-model.md
     *
     * @param class-string $class
     *
     * @return array<string, FieldMetadata> property name => field, in declaration order, the parents' first
     */
    public function readContentType(string $class, bool $translation): array
    {
        $classes = [];
        for ($reflection = new \ReflectionClass($class); false !== $reflection; $reflection = $reflection->getParentClass()) {
            array_unshift($classes, $reflection);
        }

        $fields = [];
        foreach ($classes as $reflection) {
            $declared = array_filter($reflection->getProperties(), static fn (\ReflectionProperty $property): bool => $property->getDeclaringClass()->getName() === $reflection->getName());
            $fields = [...$fields, ...self::fields($declared, $translation)];
        }

        return $fields;
    }

    /**
     * @param iterable<\ReflectionProperty> $properties
     * @param bool|null $translatable what every field is, or null to resolve it from the field or else the field type
     *
     * @return array<string, FieldMetadata>
     */
    private static function fields(iterable $properties, ?bool $translatable = null): array
    {
        $fields = [];

        foreach ($properties as $property) {
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
