<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\DependencyInjection\Compiler;

use Setono\SyliusCMSPlugin\Binding\BindablePropertyInterface;
use Setono\SyliusCMSPlugin\Binding\BindingType;
use Setono\SyliusCMSPlugin\Registry\AttributeReader;
use Setono\SyliusCMSPlugin\Registry\BindablePropertyMetadata;
use Setono\SyliusCMSPlugin\Registry\BindablePropertyRegistry;
use Setono\SyliusCMSPlugin\Registry\Code;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Hands the bindable properties to their registry by binding ("<context key>.<code>"), in the order the picker lists
 * them: by their tag's priority, highest first, which puts the shipped ones before a project's, and else in the order
 * they're defined in. A service carrying #[AsBindableProperty] is declared by it, which autoconfiguration tags; one
 * that setono_sylius_cms.bindable_properties declares carries its declaration in its tag. Two properties with the same
 * binding fail the container build, and so does one that doesn't implement BindablePropertyInterface, has an invalid
 * code or type, a parameters class that doesn't exist, or reads a context key the config doesn't declare (the channel
 * and the customer are always there). See "Bindable properties" in docs/spec/extending.md
 */
final class RegisterBindablePropertiesPass implements CompilerPassInterface
{
    public const TAG = 'setono_sylius_cms.bindable_property';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(BindablePropertyRegistry::class)) {
            return;
        }

        /** @var array<string, array{context_key: string, code: string, label: string, type: string, params: class-string|null}> $declarations */
        $declarations = [];

        /** @var array<string, array{string, class-string, bool}> $declaredBy binding => [service id, class, declared by its tag] */
        $declaredBy = [];

        /** @var array<string, Reference> $services */
        $services = [];

        foreach (TaggedClasses::byPriority($container, self::TAG, 'bindable property') as $id => $class) {
            [$declaration, $byTag] = self::declaration($container, $id, $class);
            self::assertValid($container, $class, $declaration);

            $binding = sprintf('%s.%s', $declaration['context_key'], $declaration['code']);
            if (isset($declaredBy[$binding])) {
                [, $otherClass, $otherByTag] = $declaredBy[$binding];
                if ($byTag || $otherByTag || $otherClass !== $class) {
                    throw new \LogicException(sprintf('The bindable property "%s" is declared twice, by %s and by %s. Bindings are unique, so give one of them another code', $binding, $otherClass, $class));
                }
            }

            $declaredBy[$binding] = [$id, $class, $byTag];
            $declarations[$binding] = $declaration;
            $services[$binding] = new Reference($id);
        }

        $container->getDefinition(BindablePropertyRegistry::class)
            ->replaceArgument(0, $declarations)
            ->replaceArgument(2, ServiceLocatorTagPass::register($container, $services))
        ;
    }

    /**
     * The property's declaration: its tag's, when it has a code, as a property setono_sylius_cms.bindable_properties
     * declares has, and else its class's #[AsBindableProperty]
     *
     * @param class-string $class
     *
     * @return array{array{context_key: string, code: string, label: string, type: string, params: class-string|null}, bool} the declaration, and whether its tag declares it
     */
    private static function declaration(ContainerBuilder $container, string $id, string $class): array
    {
        $tag = $container->getDefinition($id)->getTag(self::TAG)[0] ?? [];
        if (isset($tag['code'])) {
            $params = $tag['params'] ?? null;

            /** @var class-string|null $params */
            return [[
                'context_key' => self::string($tag['context_key'] ?? null),
                'code' => self::string($tag['code']),
                'label' => self::string($tag['label'] ?? null),
                'type' => self::string($tag['type'] ?? null),
                'params' => is_string($params) ? $params : null,
            ], true];
        }

        $attribute = AttributeReader::bindableProperty($class) ?? throw new \LogicException(sprintf('The service "%s" is tagged "%s", but its class %s has no #[AsBindableProperty]', $id, self::TAG, $class));

        return [[
            'context_key' => $attribute->contextKey,
            'code' => $attribute->code,
            'label' => $attribute->label,
            'type' => $attribute->type->value,
            'params' => $attribute->params,
        ], false];
    }

    /**
     * @param class-string $class
     * @param array{context_key: string, code: string, label: string, type: string, params: class-string|null} $declaration
     */
    private static function assertValid(ContainerBuilder $container, string $class, array $declaration): void
    {
        $what = sprintf('The bindable property %s', $class);

        foreach (['context key' => $declaration['context_key'], 'code' => $declaration['code']] as $part => $code) {
            if (!Code::isValid($code)) {
                throw new \LogicException(sprintf('The %s "%s" of the bindable property %s must match %s', $part, $code, $class, Code::PATTERN));
            }
        }

        if ('' === $declaration['label']) {
            throw new \LogicException(sprintf('%s "%s.%s" needs a label', $what, $declaration['context_key'], $declaration['code']));
        }

        if (null === BindingType::tryFrom($declaration['type'])) {
            throw new \LogicException(sprintf('%s has the type "%s", which is none of %s', $what, $declaration['type'], implode(', ', array_map(static fn (BindingType $type): string => $type->value, BindingType::cases()))));
        }

        if (!is_a($class, BindablePropertyInterface::class, true)) {
            throw new \LogicException(sprintf('%s must implement %s', $what, BindablePropertyInterface::class));
        }

        if (null !== $declaration['params'] && !class_exists($declaration['params'])) {
            throw new \LogicException(sprintf('The parameters class %s of the bindable property %s doesn\'t exist', $declaration['params'], $class));
        }

        if (!in_array($declaration['context_key'], BindablePropertyMetadata::EVERYWHERE, true)) {
            DeclaredContextKeys::assert($container, [$declaration['context_key']], $what);
        }
    }

    private static function string(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
