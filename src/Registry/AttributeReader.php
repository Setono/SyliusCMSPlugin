<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Registry;

use Setono\SyliusCMSPlugin\Attribute\AsUpgrader;
use Setono\SyliusCMSPlugin\Attribute\BlockType;
use Setono\SyliusCMSPlugin\Attribute\ContentType;
use Setono\SyliusCMSPlugin\Attribute\Layout;

/**
 * Reads the plugin's class attributes. A content type subclass keeps the shipped #[ContentType]:
 * the attribute is read from the nearest class in the hierarchy that has one
 */
final class AttributeReader
{
    /**
     * @param class-string $class
     */
    public static function blockType(string $class): ?BlockType
    {
        foreach ((new \ReflectionClass($class))->getAttributes(BlockType::class) as $attribute) {
            return $attribute->newInstance();
        }

        return null;
    }

    /**
     * @param class-string $class
     */
    public static function upgrader(string $class): ?AsUpgrader
    {
        foreach ((new \ReflectionClass($class))->getAttributes(AsUpgrader::class) as $attribute) {
            return $attribute->newInstance();
        }

        return null;
    }

    /**
     * @param class-string $class
     */
    public static function layout(string $class): ?Layout
    {
        foreach ((new \ReflectionClass($class))->getAttributes(Layout::class) as $attribute) {
            return $attribute->newInstance();
        }

        return null;
    }

    /**
     * @param class-string $class
     */
    public static function contentType(string $class): ?ContentType
    {
        for ($reflection = new \ReflectionClass($class); false !== $reflection; $reflection = $reflection->getParentClass()) {
            foreach ($reflection->getAttributes(ContentType::class) as $attribute) {
                return $attribute->newInstance();
            }
        }

        return null;
    }
}
