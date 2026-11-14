<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\DependencyInjection\Compiler;

use Setono\SyliusCMSPlugin\Registry\AttributeReader;
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
 * below the code's, or the container build fails. See "Upgraders" in docs/spec/extending.md
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

        foreach (array_keys($container->findTaggedServiceIds(self::TAG)) as $id) {
            $class = $container->getParameterBag()->resolveValue($container->getDefinition($id)->getClass() ?? $id);
            $reflection = is_string($class) ? $container->getReflectionClass($class) : null;
            if (null === $reflection) {
                throw new \LogicException(sprintf('The upgrader service "%s" must have an existing class', $id));
            }

            $class = $reflection->getName();
            if (!$reflection->implementsInterface(UpgraderInterface::class)) {
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

        $container->getDefinition(StoredDataUpgrader::class)->replaceArgument(3, ServiceLocatorTagPass::register($container, $upgraders));
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
