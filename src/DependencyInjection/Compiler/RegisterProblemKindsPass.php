<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\DependencyInjection\Compiler;

use Setono\SyliusCMSPlugin\Command\LintCommand;
use Setono\SyliusCMSPlugin\Problem\ProblemCountFingerprint;
use Setono\SyliusCMSPlugin\Problem\ProblemCountListener;
use Setono\SyliusCMSPlugin\Problem\ProblemCountProvider;
use Setono\SyliusCMSPlugin\Problem\ProblemCountUpdater;
use Setono\SyliusCMSPlugin\Problem\ProblemFinder;
use Setono\SyliusCMSPlugin\Problem\ReferenceProblemKindInterface;
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
 * Those of them about references go to the problem count listener, with the classes they reference and the fields
 * they refer to them by. A tag's `priority` orders them. See "Problems" in docs/spec/admin.md
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

        // What the kinds about references read is known now, so a flush is checked without building a kind
        if ($container->hasDefinition(ProblemCountListener::class)) {
            $referenceKinds = $referenceKindClasses = $referencedClasses = [];
            foreach ($stored as $kind) {
                $class = $classes[(string) $kind];
                if (is_a($class, ReferenceProblemKindInterface::class, true)) {
                    $referenceKinds[] = $kind;
                    $referenceKindClasses[] = $class;
                    $referencedClasses = [...$referencedClasses, ...$class::getReferencedClasses()];
                }
            }

            $container->getDefinition(ProblemCountListener::class)
                ->setArgument(1, new IteratorArgument($referenceKinds))
                ->setArgument(2, array_values(array_unique($referencedClasses)))
                ->setArgument(3, self::referenceFields($referenceKindClasses))
            ;
        }
    }

    /**
     * The referenced classes that every kind about them refers to by a field, with those fields, so a write of one that
     * neither deletes it nor changes a field counts nothing. A class any kind reads every write of is left out
     *
     * @param list<class-string<ReferenceProblemKindInterface>> $kinds
     *
     * @return array<class-string, list<string>>
     */
    private static function referenceFields(array $kinds): array
    {
        /** @var array<class-string, list<string>|null> $fields null for a class whose every write counts */
        $fields = [];
        foreach ($kinds as $kind) {
            $byClass = $kind::getReferenceFields();
            foreach ($kind::getReferencedClasses() as $class) {
                $field = $byClass[$class] ?? null;
                $fields[$class] = null === $field || (array_key_exists($class, $fields) && null === $fields[$class])
                    ? null
                    : array_values(array_unique([...$fields[$class] ?? [], ...(array) $field]));
            }
        }

        return array_filter($fields, static fn (?array $classFields): bool => null !== $classFields);
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
