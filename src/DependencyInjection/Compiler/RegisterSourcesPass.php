<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\DependencyInjection\Compiler;

use Setono\SyliusCMSPlugin\Registry\AttributeReader;
use Setono\SyliusCMSPlugin\Registry\Code;
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
 * doesn't exist. See "Sources" in docs/spec/extending.md
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

        foreach (TaggedClasses::byPriority($container, self::TAG, 'source') as $id => $class) {
            $attribute = AttributeReader::source($class) ?? throw new \LogicException(sprintf('The service "%s" is tagged "%s", but its class %s has no #[AsSource]', $id, self::TAG, $class));
            $code = $attribute->code;

            if (!Code::isValid($code)) {
                throw new \LogicException(sprintf('The source code "%s" of %s must match %s', $code, $class, Code::PATTERN));
            }

            if (!is_a($class, SourceInterface::class, true)) {
                throw new \LogicException(sprintf('The source %s must implement %s', $class, SourceInterface::class));
            }

            if (!class_exists($attribute->of) && !interface_exists($attribute->of)) {
                throw new \LogicException(sprintf('The source %s lists %s, which is no class or interface', $class, $attribute->of));
            }

            if (null !== $attribute->params && !class_exists($attribute->params)) {
                throw new \LogicException(sprintf('The parameters class %s of the source %s doesn\'t exist', $attribute->params, $class));
            }

            if (isset($classes[$code]) && $classes[$code] !== $class) {
                throw new \LogicException(sprintf('The source code "%s" is used by both %s and %s. Codes are unique per kind, so give one of them another code', $code, $classes[$code], $class));
            }

            $classes[$code] = $class;
            $sources[$code] = new Reference($id);
        }

        $container->getDefinition(SourceRegistry::class)
            ->replaceArgument(0, $classes)
            ->replaceArgument(2, ServiceLocatorTagPass::register($container, $sources))
        ;
    }
}
