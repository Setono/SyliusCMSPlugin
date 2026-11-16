<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\DependencyInjection\Compiler;

use Setono\SyliusCMSPlugin\Command\LintCommand;
use Setono\SyliusCMSPlugin\Problem\ProblemFinder;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Hands the problem kinds, the services tagged setono_sylius_cms.problem_kind, to the problem finder, which the admin
 * reads every kind through, and those whose tag has `lint: true` to setono:cms:lint. A tag's `priority` orders them.
 * See "Problems" in docs/spec/admin.md
 */
final class RegisterProblemKindsPass implements CompilerPassInterface
{
    use PriorityTaggedServiceTrait;

    public const TAG = 'setono_sylius_cms.problem_kind';

    public function process(ContainerBuilder $container): void
    {
        $kinds = $this->findAndSortTaggedServices(self::TAG, $container);

        if ($container->hasDefinition(ProblemFinder::class)) {
            $container->getDefinition(ProblemFinder::class)->setArgument(0, new IteratorArgument($kinds));
        }

        if ($container->hasDefinition(LintCommand::class)) {
            $linted = array_values(array_filter($kinds, static fn (Reference $kind): bool => self::isLinted($container, (string) $kind)));
            $container->getDefinition(LintCommand::class)->setArgument(5, new IteratorArgument($linted));
        }
    }

    /**
     * A kind is linted when any of its tags says so, as a project's kind can carry the tag that autoconfiguration adds
     * beside its own
     */
    private static function isLinted(ContainerBuilder $container, string $id): bool
    {
        foreach ($container->getDefinition($id)->getTag(self::TAG) as $attributes) {
            if (true === ($attributes['lint'] ?? false)) {
                return true;
            }
        }

        return false;
    }
}
