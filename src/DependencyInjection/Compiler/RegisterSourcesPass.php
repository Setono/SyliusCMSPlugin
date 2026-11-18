<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\DependencyInjection\Compiler;

use Setono\SyliusCMSPlugin\Registry\AttributeReader;
use Setono\SyliusCMSPlugin\Registry\SourceRegistry;
use Setono\SyliusCMSPlugin\Source\SourceInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Hands the services carrying #[AsSource], which autoconfiguration tags, to the source registry, by code, in the order
 * the builder offers them in: by their tag's priority, highest first, which puts the shipped ones before a project's,
 * and else in the order they're defined in. Two sources with the same code fail the container build, and so does a
 * source that doesn't implement SourceInterface, that lists a class that doesn't exist or whose parameters class
 * doesn't exist, or that needs a context key the config doesn't declare. See "Sources" in docs/spec/extending.md
 */
final class RegisterSourcesPass implements CompilerPassInterface
{
    public const TAG = 'setono_sylius_cms.source';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(SourceRegistry::class)) {
            return;
        }

        /** @var array<string, class-string> $classes */
        $classes = [];

        /** @var array<string, Reference> $sources */
        $sources = [];

        foreach (TaggedClasses::byCode($container, self::TAG, 'source', '#[AsSource]', AttributeReader::source(...), SourceInterface::class) as $code => [$id, $class, $attribute]) {
            if (!class_exists($attribute->of) && !interface_exists($attribute->of)) {
                throw new \LogicException(sprintf('The source %s lists %s, which is no class or interface', $class, $attribute->of));
            }

            if (null !== $attribute->params && !class_exists($attribute->params)) {
                throw new \LogicException(sprintf('The parameters class %s of the source %s doesn\'t exist', $attribute->params, $class));
            }

            DeclaredContextKeys::assert($container, $attribute->needs, sprintf('The source %s', $class));

            $classes[$code] = $class;
            $sources[$code] = new Reference($id);
        }

        $container->getDefinition(SourceRegistry::class)
            ->replaceArgument(0, $classes)
            ->replaceArgument(2, ServiceLocatorTagPass::register($container, $sources))
        ;
    }
}
