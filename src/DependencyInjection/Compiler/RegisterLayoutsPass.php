<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\DependencyInjection\Compiler;

use Setono\SyliusCMSPlugin\Attribute\Layout;
use Setono\SyliusCMSPlugin\Registry\AttributeReader;
use Setono\SyliusCMSPlugin\Registry\Code;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistry;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Collects the classes carrying #[Layout], which autoconfiguration tags, into the layout registry, by content type and
 * code. Layouts are metadata, not shared services, so their service definitions are removed. The container build fails
 * on a layout setup the registry can't work with: two layouts of one content type with the same code, a layout of a
 * content type without its own URL, a content type with its own URL and no default layout, or with more than one, unless
 * setono_sylius_cms.content_types.<code>.default_layout names one, and a disabled default layout.
 * See "Layouts" in docs/spec/layouts-and-presets.md
 */
final class RegisterLayoutsPass implements CompilerPassInterface
{
    public const TAG = 'setono_sylius_cms.layout';

    private const DEFAULTS_PARAMETER = 'setono_sylius_cms.default_layouts';

    private const DISABLED_PARAMETER = 'setono_sylius_cms.disabled.layouts';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(LayoutRegistry::class)) {
            return;
        }

        /** @var array<string, array<string, class-string>> $classes */
        $classes = [];

        foreach (TaggedClasses::of($container, self::TAG, 'layout') as $id => $class) {
            $layout = AttributeReader::layout($class) ?? throw new \LogicException(sprintf('The service "%s" is tagged "%s", but its class %s has no #[Layout]', $id, self::TAG, $class));

            if (!Code::isValid($layout->code)) {
                throw new \LogicException(sprintf('The layout code "%s" of %s must match %s', $layout->code, $class, Code::PATTERN));
            }

            $contentType = self::contentType($layout, $class);
            $registered = $classes[$contentType][$layout->code] ?? $class;
            if ($registered !== $class) {
                throw new \LogicException(sprintf('The layout code "%s" of the content type "%s" is used by both %s and %s. Codes are unique within a content type, so give one of them another code', $layout->code, $contentType, $registered, $class));
            }

            $classes[$contentType][$layout->code] = $class;
            $container->removeDefinition($id);
        }

        self::assertDefaults($container, $classes);
        self::assertContentTypesWithUrlsHaveLayouts($container, $classes);

        $container->getDefinition(LayoutRegistry::class)->replaceArgument(0, $classes);
    }

    /**
     * The code of the content type a layout belongs to, which must have its own URL
     *
     * @param class-string $class the layout's class
     */
    public static function contentType(Layout $layout, string $class): string
    {
        $contentType = class_exists($layout->contentType) ? AttributeReader::contentType($layout->contentType) : null;
        if (null === $contentType) {
            throw new \LogicException(sprintf('The layout %s belongs to %s, which is no content type class', $class, $layout->contentType));
        }

        if (null === $contentType->prefix) {
            throw new \LogicException(sprintf('The layout %s belongs to the content type "%s", which has no URL of its own. Only content types with their own URL have layouts', $class, $contentType->code));
        }

        return $contentType->code;
    }

    /**
     * Resolves each content type's default layout the way the registry does at runtime, so a setup it can't resolve
     * fails the build
     *
     * @param array<string, array<string, class-string>> $classes
     */
    private static function assertDefaults(ContainerBuilder $container, array $classes): void
    {
        /** @var array<string, string> $defaults */
        $defaults = $container->hasParameter(self::DEFAULTS_PARAMETER) ? $container->getParameter(self::DEFAULTS_PARAMETER) : [];
        /** @var list<string> $disabled */
        $disabled = $container->hasParameter(self::DISABLED_PARAMETER) ? $container->getParameter(self::DISABLED_PARAMETER) : [];

        foreach ($defaults as $contentType => $code) {
            if (!isset($classes[$contentType])) {
                throw new \LogicException(sprintf('setono_sylius_cms.content_types.%1$s.default_layout names the layout "%2$s", but the content type "%1$s" has no layouts', $contentType, $code));
            }
        }

        $registry = new LayoutRegistry($classes, new FieldReader(), $defaults, $disabled);
        foreach (array_keys($classes) as $contentType) {
            $registry->getDefault($contentType);
        }
    }

    /**
     * A content type with its own URL renders in a layout, so it needs one. The content types are known once
     * RegisterContentTypesPass has handed them to their registry
     *
     * @param array<string, array<string, class-string>> $classes
     */
    private static function assertContentTypesWithUrlsHaveLayouts(ContainerBuilder $container, array $classes): void
    {
        if (!$container->hasDefinition(ContentTypeRegistry::class)) {
            return;
        }

        /** @var array<string, array{model: class-string}> $contentTypes */
        $contentTypes = $container->getDefinition(ContentTypeRegistry::class)->getArgument(0);
        foreach ($contentTypes as $code => $contentType) {
            if (!isset($classes[$code]) && null !== AttributeReader::contentType($contentType['model'])?->prefix) {
                throw new \LogicException(sprintf('The content type "%s" has its own URL, so it needs a default layout. Add a class with #[Layout(contentType: %s::class, default: true)]', $code, $contentType['model']));
            }
        }
    }
}
