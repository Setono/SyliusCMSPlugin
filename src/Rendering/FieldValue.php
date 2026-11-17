<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Rendering;

/**
 * A hydrated block's or layout's value of a field: its public property, or null while it's uninitialised, as it is for
 * a missing value
 *
 * @internal
 */
final class FieldValue
{
    private function __construct()
    {
    }

    /**
     * @throws \InvalidArgumentException when the object has no public property by that name
     */
    public static function read(object $subject, string $name): mixed
    {
        if (!property_exists($subject, $name)) {
            throw new \InvalidArgumentException(sprintf('%s has no field "%s"', $subject::class, $name));
        }

        $property = new \ReflectionProperty($subject, $name);
        if (!$property->isPublic()) {
            throw new \InvalidArgumentException(sprintf('The field "%s" of %s must be a public property', $name, $subject::class));
        }

        return $property->isInitialized($subject) ? $property->getValue($subject) : null;
    }
}
