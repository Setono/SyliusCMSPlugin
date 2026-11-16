<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\DependencyInjection\Compiler;

use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractCompilerPassTestCase;
use Setono\SyliusCMSPlugin\Command\LintCommand;
use Setono\SyliusCMSPlugin\DependencyInjection\Compiler\RegisterProblemKindsPass;
use Setono\SyliusCMSPlugin\Problem\Kind\MisfitValue;
use Setono\SyliusCMSPlugin\Problem\Kind\UnknownBlockType;
use Setono\SyliusCMSPlugin\Problem\ProblemFinder;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class RegisterProblemKindsPassTest extends AbstractCompilerPassTestCase
{
    protected function registerCompilerPass(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new RegisterProblemKindsPass());
    }

    /**
     * @test
     */
    public function it_hands_every_kind_to_the_problem_finder_and_those_tagged_for_lint_to_the_lint_command_by_priority(): void
    {
        $this->setDefinition(ProblemFinder::class, new Definition(ProblemFinder::class, [[], null]));
        $this->setDefinition(LintCommand::class, new Definition(LintCommand::class, [null, null, null, null, null, []]));
        $this->setDefinition('app.unlinted', (new Definition(UnknownBlockType::class))->addTag('setono_sylius_cms.problem_kind'));
        $this->setDefinition('app.linted', (new Definition(MisfitValue::class))->addTag('setono_sylius_cms.problem_kind', ['lint' => true]));
        $this->setDefinition('app.first', (new Definition(UnknownBlockType::class))->addTag('setono_sylius_cms.problem_kind', ['priority' => 10, 'lint' => true]));
        $this->setDefinition('app.not_linted', (new Definition(UnknownBlockType::class))->addTag('setono_sylius_cms.problem_kind', ['lint' => false]));
        // A project's kind, which autoconfiguration tags beside the tag it carries for lint
        $this->setDefinition('app.project', (new Definition(MisfitValue::class))->addTag('setono_sylius_cms.problem_kind')->addTag('setono_sylius_cms.problem_kind', ['lint' => true]));

        $this->compile();

        self::assertEquals(
            new IteratorArgument([new Reference('app.first'), new Reference('app.unlinted'), new Reference('app.linted'), new Reference('app.not_linted'), new Reference('app.project')]),
            $this->container->getDefinition(ProblemFinder::class)->getArgument(0),
        );
        self::assertEquals(
            new IteratorArgument([new Reference('app.first'), new Reference('app.linted'), new Reference('app.project')]),
            $this->container->getDefinition(LintCommand::class)->getArgument(5),
        );
    }

    /**
     * @test
     */
    public function it_does_nothing_without_the_finder_and_the_command(): void
    {
        $this->setDefinition('app.kind', (new Definition(UnknownBlockType::class))->addTag('setono_sylius_cms.problem_kind', ['lint' => true]));

        $this->compile();

        $this->assertContainerBuilderNotHasService(ProblemFinder::class);
        $this->assertContainerBuilderNotHasService(LintCommand::class);
    }
}
