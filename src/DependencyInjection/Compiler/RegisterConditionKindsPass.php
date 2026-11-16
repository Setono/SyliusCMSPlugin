<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\DependencyInjection\Compiler;

use Setono\SyliusCMSPlugin\Condition\ConditionKindInterface;
use Setono\SyliusCMSPlugin\Condition\Reads;
use Setono\SyliusCMSPlugin\Condition\TimeDependentConditionKindInterface;
use Setono\SyliusCMSPlugin\Registry\AttributeReader;
use Setono\SyliusCMSPlugin\Registry\Code;
use Setono\SyliusCMSPlugin\Registry\ConditionKindRegistry;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Hands the services carrying #[AsConditionKind], which autoconfiguration tags, to the condition kind registry, by code.
 * Two condition kinds with the same code fail the container build, and so does a kind that doesn't implement
 * ConditionKindInterface, or that reads the time without implementing TimeDependentConditionKindInterface.
 * See "Condition kinds" in docs/spec/extending.md
 */
final class RegisterConditionKindsPass implements CompilerPassInterface
{
    public const TAG = 'setono_sylius_cms.condition_kind';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(ConditionKindRegistry::class)) {
            return;
        }

        /** @var array<string, class-string> $classes */
        $classes = [];

        /** @var array<string, Reference> $kinds */
        $kinds = [];

        foreach (TaggedClasses::of($container, self::TAG, 'condition kind') as $id => $class) {
            $attribute = AttributeReader::conditionKind($class) ?? throw new \LogicException(sprintf('The service "%s" is tagged "%s", but its class %s has no #[AsConditionKind]', $id, self::TAG, $class));
            $code = $attribute->code;

            if (!Code::isValid($code)) {
                throw new \LogicException(sprintf('The condition kind code "%s" of %s must match %s', $code, $class, Code::PATTERN));
            }

            if (!is_a($class, ConditionKindInterface::class, true)) {
                throw new \LogicException(sprintf('The condition kind %s must implement %s', $class, ConditionKindInterface::class));
            }

            if (in_array(Reads::Time, $attribute->reads ?? [], true) && !is_a($class, TimeDependentConditionKindInterface::class, true)) {
                throw new \LogicException(sprintf('The condition kind %s reads the time, so it must implement %s', $class, TimeDependentConditionKindInterface::class));
            }

            if (null !== $attribute->params && !class_exists($attribute->params)) {
                throw new \LogicException(sprintf('The parameters class %s of the condition kind %s doesn\'t exist', $attribute->params, $class));
            }

            if (isset($classes[$code]) && $classes[$code] !== $class) {
                throw new \LogicException(sprintf('The condition kind code "%s" is used by both %s and %s. Codes are unique per kind, so give one of them another code', $code, $classes[$code], $class));
            }

            $classes[$code] = $class;
            $kinds[$code] = new Reference($id);
        }

        $container->getDefinition(ConditionKindRegistry::class)
            ->replaceArgument(0, $classes)
            ->replaceArgument(2, ServiceLocatorTagPass::register($container, $kinds))
        ;
    }
}
