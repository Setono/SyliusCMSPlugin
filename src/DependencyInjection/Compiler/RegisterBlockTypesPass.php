<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\DependencyInjection\Compiler;

use Setono\SyliusCMSPlugin\Attribute\BlockType;
use Setono\SyliusCMSPlugin\Registry\AttributeReader;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Collects the classes carrying #[BlockType], which autoconfiguration tags, into the block type registry.
 * Block types are metadata, not shared services, so their service definitions are removed. Two block types
 * with the same code fail the container build, and so do block styles added in config to a block type that doesn't exist
 */
final class RegisterBlockTypesPass implements CompilerPassInterface
{
    public const TAG = 'setono_sylius_cms.block_type';

    private const CODE_PATTERN = '/^[a-z][a-z0-9_]*$/';

    private const BLOCK_STYLES_PARAMETER = 'setono_sylius_cms.block_styles';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(BlockTypeRegistry::class)) {
            return;
        }

        /** @var array<string, class-string> $classes */
        $classes = [];

        foreach (array_keys($container->findTaggedServiceIds(self::TAG)) as $id) {
            $class = $container->getParameterBag()->resolveValue($container->getDefinition($id)->getClass() ?? $id);
            $reflection = is_string($class) ? $container->getReflectionClass($class) : null;
            if (null === $reflection) {
                throw new \LogicException(sprintf('The block type service "%s" must have an existing class', $id));
            }

            $class = $reflection->getName();
            $attribute = AttributeReader::blockType($class) ?? throw new \LogicException(sprintf('The service "%s" is tagged "%s", but its class %s has no #[BlockType]', $id, self::TAG, $class));
            $code = $attribute->code;

            if (1 !== preg_match(self::CODE_PATTERN, $code)) {
                throw new \LogicException(sprintf('The block type code "%s" of %s must match %s', $code, $class, self::CODE_PATTERN));
            }

            self::assertStyles($attribute, $class);

            if (isset($classes[$code]) && $classes[$code] !== $class) {
                throw new \LogicException(sprintf('The block type code "%s" is used by both %s and %s. Codes are unique per kind, so give one of them another code', $code, $classes[$code], $class));
            }

            $classes[$code] = $class;
            $container->removeDefinition($id);
        }

        self::assertConfiguredStylesHaveBlockTypes($container, $classes);

        $container->getDefinition(BlockTypeRegistry::class)->replaceArgument(0, $classes);
    }

    /**
     * @param class-string $class
     */
    private static function assertStyles(BlockType $attribute, string $class): void
    {
        $codes = [];
        foreach ($attribute->styles as $style) {
            if (1 !== preg_match(self::CODE_PATTERN, $style->code)) {
                throw new \LogicException(sprintf('The block style code "%s" of %s must match %s', $style->code, $class, self::CODE_PATTERN));
            }

            if (isset($codes[$style->code])) {
                throw new \LogicException(sprintf('%s declares the block style "%s" more than once', $class, $style->code));
            }

            $codes[$style->code] = true;
        }
    }

    /**
     * @param array<string, class-string> $classes
     */
    private static function assertConfiguredStylesHaveBlockTypes(ContainerBuilder $container, array $classes): void
    {
        if (!$container->hasParameter(self::BLOCK_STYLES_PARAMETER)) {
            return;
        }

        $blockStyles = $container->getParameter(self::BLOCK_STYLES_PARAMETER);
        foreach (array_keys(is_array($blockStyles) ? $blockStyles : []) as $code) {
            if (!isset($classes[$code])) {
                throw new \LogicException(sprintf('%s adds styles to the block type "%s", but there is no block type with that code', self::BLOCK_STYLES_PARAMETER, $code));
            }
        }
    }
}
