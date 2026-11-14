<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\DependencyInjection\Compiler;

use Setono\SyliusCMSPlugin\Attribute\Layout;
use Setono\SyliusCMSPlugin\Registry\AttributeReader;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistry;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Collects the classes carrying #[Layout], which autoconfiguration tags, into the layout registry, by content type and
 * code. Layouts are metadata, not shared services, so their service definitions are removed. Two layouts of one
 * content type with the same code fail the container build
 */
final class RegisterLayoutsPass implements CompilerPassInterface
{
    public const TAG = 'setono_sylius_cms.layout';

    private const CODE_PATTERN = '/^[a-z][a-z0-9_]*$/';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(LayoutRegistry::class)) {
            return;
        }

        /** @var array<string, array<string, class-string>> $classes */
        $classes = [];

        foreach (array_keys($container->findTaggedServiceIds(self::TAG)) as $id) {
            $class = $container->getParameterBag()->resolveValue($container->getDefinition($id)->getClass() ?? $id);
            $reflection = is_string($class) ? $container->getReflectionClass($class) : null;
            if (null === $reflection) {
                throw new \LogicException(sprintf('The layout service "%s" must have an existing class', $id));
            }

            $class = $reflection->getName();
            $layout = AttributeReader::layout($class) ?? throw new \LogicException(sprintf('The service "%s" is tagged "%s", but its class %s has no #[Layout]', $id, self::TAG, $class));

            if (1 !== preg_match(self::CODE_PATTERN, $layout->code)) {
                throw new \LogicException(sprintf('The layout code "%s" of %s must match %s', $layout->code, $class, self::CODE_PATTERN));
            }

            $contentType = self::contentType($layout, $class);
            $registered = $classes[$contentType][$layout->code] ?? $class;
            if ($registered !== $class) {
                throw new \LogicException(sprintf('The layout code "%s" of the content type "%s" is used by both %s and %s. Codes are unique within a content type, so give one of them another code', $layout->code, $contentType, $registered, $class));
            }

            $classes[$contentType][$layout->code] = $class;
            $container->removeDefinition($id);
        }

        $container->getDefinition(LayoutRegistry::class)->replaceArgument(0, $classes);
    }

    /**
     * The code of the content type a layout belongs to
     *
     * @param class-string $class the layout's class
     */
    public static function contentType(Layout $layout, string $class): string
    {
        $contentType = class_exists($layout->contentType) ? AttributeReader::contentType($layout->contentType) : null;

        return $contentType->code ?? throw new \LogicException(sprintf('The layout %s belongs to %s, which is no content type class', $class, $layout->contentType));
    }
}
