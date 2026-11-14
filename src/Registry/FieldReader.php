<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Registry;

use Setono\SyliusCMSPlugin\Attribute\FieldType;
use Setono\SyliusCMSPlugin\Field\Field;

/**
 * Reads the fields of a class: its public, typed properties that carry a field attribute
 */
final class FieldReader
{
    /**
     * @param class-string $class
     *
     * @return array<string, FieldMetadata> property name => field, in declaration order
     */
    public function read(string $class): array
    {
        $fields = [];

        foreach ((new \ReflectionClass($class))->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
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
                    $field->translatable ?? $type->translatable,
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
