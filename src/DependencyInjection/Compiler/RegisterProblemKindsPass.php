<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\DependencyInjection\Compiler;

use Setono\SyliusCMSPlugin\Command\LintCommand;
use Setono\SyliusCMSPlugin\Problem\ProblemCountFingerprint;
use Setono\SyliusCMSPlugin\Problem\ProblemCountProvider;
use Setono\SyliusCMSPlugin\Problem\ProblemCountUpdater;
use Setono\SyliusCMSPlugin\Problem\ProblemFinder;
use Setono\SyliusCMSPlugin\Problem\TimeDependentProblemKindInterface;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Hands the problem kinds, the services tagged setono_sylius_cms.problem_kind, to the problem finder, which the
 * overview page reads every kind through, and those whose tag has `lint: true` to setono:cms:lint. The time-dependent
 * kinds go to the problem count provider, which finds their problems when it reads the counts, and the others to the
 * problem count updater, which stores theirs, with their classes to the fingerprint of what the counts depend on.
 * A tag's `priority` orders them. See "Problems" in docs/spec/admin.md
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

        // A time-dependent kind's problems are found when the counts are read, and every other kind's are stored
        $classes = TaggedClasses::of($container, self::TAG, 'problem kind');
        $timeDependent = $stored = [];
        foreach ($kinds as $kind) {
            if (is_a($classes[(string) $kind], TimeDependentProblemKindInterface::class, true)) {
                $timeDependent[] = $kind;
            } else {
                $stored[] = $kind;
            }
        }

        if ($container->hasDefinition(ProblemCountUpdater::class)) {
            $container->getDefinition(ProblemCountUpdater::class)->setArgument(0, new IteratorArgument($stored));
        }

        if ($container->hasDefinition(ProblemCountFingerprint::class)) {
            $container->getDefinition(ProblemCountFingerprint::class)->setArgument(0, array_map(static fn (Reference $kind): string => $classes[(string) $kind], $stored));
        }

        if ($container->hasDefinition(ProblemCountProvider::class)) {
            $container->getDefinition(ProblemCountProvider::class)->setArgument(2, new IteratorArgument($timeDependent));
        }
    }

    /**
     * A kind is linted when any of its tags says so, as a project's kind can carry the tag that autoconfiguration adds
     * beside its own
     */
    private static function isLinted(ContainerBuilder $container, string $id): bool
    {
        foreach ($container->getDefinition($id)->getTag(self::TAG) as $attributes) {
            if (is_array($attributes) && true === ($attributes['lint'] ?? false)) {
                return true;
            }
        }

        return false;
    }
}
