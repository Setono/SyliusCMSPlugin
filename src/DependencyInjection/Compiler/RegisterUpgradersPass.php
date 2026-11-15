<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\DependencyInjection\Compiler;

use Setono\SyliusCMSPlugin\Registry\AttributeReader;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistry;
use Setono\SyliusCMSPlugin\Upgrade\Kind;
use Setono\SyliusCMSPlugin\Upgrade\StoredDataUpgrader;
use Setono\SyliusCMSPlugin\Upgrade\UpgraderInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Hands the services carrying #[AsUpgrader], which autoconfiguration tags, to the upgrade service, by the block type,
 * layout or content type they upgrade and the version they upgrade from. Each step has one upgrader, from a version
 * below the code's, and a block type, layout or content type at version n has one for each step from 1 to n - 1, or the
 * container build fails. See "Upgraders" in docs/spec/extending.md
 */
final class RegisterUpgradersPass implements CompilerPassInterface
{
    public const TAG = 'setono_sylius_cms.upgrader';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(StoredDataUpgrader::class)) {
            return;
        }

        /** @var array<string, Reference> $upgraders */
        $upgraders = [];

        /** @var array<string, class-string> $classes */
        $classes = [];

        foreach (TaggedClasses::of($container, self::TAG, 'upgrader') as $id => $class) {
            if (!is_a($class, UpgraderInterface::class, true)) {
                throw new \LogicException(sprintf('The upgrader %s must implement %s', $class, UpgraderInterface::class));
            }

            $attribute = AttributeReader::upgrader($class) ?? throw new \LogicException(sprintf('The service "%s" is tagged "%s", but its class %s has no #[AsUpgrader]', $id, self::TAG, $class));
            [$kind, $subject, $code, $version] = self::upgrades($class, $attribute->of);
            $what = sprintf('the %s "%s"', str_replace('_', ' ', $kind->value), $code);

            if ($attribute->from < 1 || $attribute->from >= $version) {
                throw new \LogicException(sprintf('The upgrader %s upgrades %s from version %d, but it must upgrade from a version from 1 to %d, below the %s\'s version %d. Bump the version when you add an upgrader', $class, $what, $attribute->from, $version - 1, str_replace('_', ' ', $kind->value), $version));
            }

            $upgraderId = $kind->upgraderId($subject, $attribute->from);
            if (isset($classes[$upgraderId])) {
                if ($classes[$upgraderId] !== $class) {
                    throw new \LogicException(sprintf('Both %s and %s upgrade %s from version %d. Each step has one upgrader', $classes[$upgraderId], $class, $what, $attribute->from));
                }

                continue;
            }

            $classes[$upgraderId] = $class;
            $upgraders[$upgraderId] = new Reference($id);
        }

        foreach (self::versioned($container) as $class) {
            [$kind, $subject, $code, $version] = self::upgrades($class, $class);
            for ($from = 1; $from < $version; ++$from) {
                if (!isset($upgraders[$kind->upgraderId($subject, $from)])) {
                    throw new \LogicException(sprintf('The %s "%s" is at version %d, but no upgrader upgrades it from version %d. Each version bump needs an upgrader from the version before it', str_replace('_', ' ', $kind->value), $code, $version, $from));
                }
            }
        }

        $container->getDefinition(StoredDataUpgrader::class)->replaceArgument(3, ServiceLocatorTagPass::register($container, $upgraders));
    }

    /**
     * The classes of the block types, layouts and content types the other passes registered, which all have a version
     *
     * @return list<class-string>
     */
    private static function versioned(ContainerBuilder $container): array
    {
        /** @var array<string, class-string> $blockTypes code => class */
        $blockTypes = $container->hasDefinition(BlockTypeRegistry::class) ? $container->getDefinition(BlockTypeRegistry::class)->getArgument(0) : [];

        /** @var array<string, array<string, class-string>> $layouts content type code => layout code => class */
        $layouts = $container->hasDefinition(LayoutRegistry::class) ? $container->getDefinition(LayoutRegistry::class)->getArgument(0) : [];

        /** @var array<string, array{model: class-string}> $contentTypes code => resource */
        $contentTypes = $container->hasDefinition(ContentTypeRegistry::class) ? $container->getDefinition(ContentTypeRegistry::class)->getArgument(0) : [];

        return [
            ...array_values($blockTypes),
            ...array_merge(...array_values(array_map(array_values(...), $layouts))),
            ...array_values(array_map(static fn (array $contentType): string => $contentType['model'], $contentTypes)),
        ];
    }

    /**
     * What #[AsUpgrader(of)] names: its kind, the code its upgraders are registered under (Kind::upgraderId()), its
     * code and its version
     *
     * @param class-string $upgrader
     *
     * @return array{Kind, string, string, int}
     */
    private static function upgrades(string $upgrader, string $of): array
    {
        if (class_exists($of)) {
            $blockType = AttributeReader::blockType($of);
            if (null !== $blockType) {
                return [Kind::BlockType, $blockType->code, $blockType->code, $blockType->version];
            }

            $layout = AttributeReader::layout($of);
            if (null !== $layout) {
                $contentType = RegisterLayoutsPass::contentType($layout, $of);

                return [Kind::Layout, $contentType . ':' . $layout->code, $layout->code, $layout->version];
            }

            $contentType = AttributeReader::contentType($of);
            if (null !== $contentType) {
                return [Kind::ContentType, $contentType->code, $contentType->code, $contentType->version];
            }
        }

        throw new \LogicException(sprintf('The upgrader %s upgrades %s, which is no block type, layout or content type class', $upgrader, $of));
    }
}
