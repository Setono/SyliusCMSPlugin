<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\DependencyInjection\Compiler;

use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractCompilerPassTestCase;
use Setono\SyliusCMSPlugin\Command\LintCommand;
use Setono\SyliusCMSPlugin\DependencyInjection\Compiler\RegisterProblemKindsPass;
use Setono\SyliusCMSPlugin\Problem\Kind\DeletedCustomerGroup;
use Setono\SyliusCMSPlugin\Problem\Kind\MisfitValue;
use Setono\SyliusCMSPlugin\Problem\Kind\ScheduledPublishOverdue;
use Setono\SyliusCMSPlugin\Problem\Kind\UnknownBlockType;
use Setono\SyliusCMSPlugin\Problem\ProblemCountFingerprint;
use Setono\SyliusCMSPlugin\Problem\ProblemCountListener;
use Setono\SyliusCMSPlugin\Problem\ProblemCountProvider;
use Setono\SyliusCMSPlugin\Problem\ProblemCountUpdater;
use Setono\SyliusCMSPlugin\Problem\ProblemFinder;
use Setono\SyliusCMSPlugin\Tests\Unit\Problem\Fixture\ChannelAndTaxonReferences;
use Setono\SyliusCMSPlugin\Tests\Unit\Problem\Fixture\CustomerGroupReferences;
use Setono\SyliusCMSPlugin\Tests\Unit\Problem\Fixture\ProductAndTaxonCodeReferences;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Customer\Model\CustomerGroupInterface;
use Sylius\Component\Product\Model\ProductInterface;
use Sylius\Component\Taxonomy\Model\TaxonInterface;
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
    public function it_hands_the_kinds_whose_problems_are_stored_to_the_count_updater_and_their_classes_to_the_fingerprint_and_the_time_dependent_ones_to_the_count_provider(): void
    {
        $this->setDefinition(ProblemCountUpdater::class, new Definition(ProblemCountUpdater::class, [[], null, null, null]));
        $this->setDefinition(ProblemCountFingerprint::class, new Definition(ProblemCountFingerprint::class, [[], null, null, null, null]));
        $this->setDefinition(ProblemCountProvider::class, new Definition(ProblemCountProvider::class, [null, null, [], null]));
        $this->setParameter('app.kind.class', MisfitValue::class);
        $this->setDefinition('app.unknown', (new Definition(UnknownBlockType::class))->addTag('setono_sylius_cms.problem_kind'));
        $this->setDefinition('app.overdue', (new Definition(ScheduledPublishOverdue::class))->addTag('setono_sylius_cms.problem_kind'));
        $this->setDefinition('app.misfit', (new Definition('%app.kind.class%'))->addTag('setono_sylius_cms.problem_kind', ['priority' => 10]));

        $this->compile();

        self::assertEquals(
            new IteratorArgument([new Reference('app.misfit'), new Reference('app.unknown')]),
            $this->container->getDefinition(ProblemCountUpdater::class)->getArgument(0),
        );
        self::assertSame([MisfitValue::class, UnknownBlockType::class], $this->container->getDefinition(ProblemCountFingerprint::class)->getArgument(0));
        self::assertEquals(new IteratorArgument([new Reference('app.overdue')]), $this->container->getDefinition(ProblemCountProvider::class)->getArgument(2));
    }

    /**
     * @test
     */
    public function it_hands_the_kinds_about_what_entries_reference_and_what_they_reference_to_the_count_listener(): void
    {
        $this->setDefinition(ProblemCountListener::class, new Definition(ProblemCountListener::class, [null, [], [], []]));
        $this->setDefinition('app.unknown', (new Definition(UnknownBlockType::class))->addTag('setono_sylius_cms.problem_kind'));
        $this->setDefinition('app.customer_groups', (new Definition(CustomerGroupReferences::class))->addTag('setono_sylius_cms.problem_kind', ['priority' => 10]));
        $this->setDefinition('app.channels_and_taxons', (new Definition(ChannelAndTaxonReferences::class))->addTag('setono_sylius_cms.problem_kind'));
        $this->setDefinition('app.deleted_customer_group', (new Definition(DeletedCustomerGroup::class))->addTag('setono_sylius_cms.problem_kind', ['priority' => 5]));

        $this->compile();

        $listener = $this->container->getDefinition(ProblemCountListener::class);
        self::assertEquals(
            new IteratorArgument([new Reference('app.customer_groups'), new Reference('app.deleted_customer_group'), new Reference('app.channels_and_taxons')]),
            $listener->getArgument(1),
        );
        // Each once
        self::assertSame([CustomerGroupInterface::class, ChannelInterface::class, TaxonInterface::class], $listener->getArgument(2));
        self::assertSame([], $listener->getArgument(3), 'Every write of what the kinds reference counts');
    }

    /**
     * A class that every kind about it refers to by a field counts only when one is deleted or the field changes, and
     * one that any kind reads every write of counts on every write
     *
     * @test
     */
    public function it_hands_the_count_listener_the_fields_that_every_kind_about_a_class_refers_to_it_by(): void
    {
        $this->setDefinition(ProblemCountListener::class, new Definition(ProblemCountListener::class, [null, [], [], []]));
        $this->setDefinition('app.codes', (new Definition(ProductAndTaxonCodeReferences::class))->addTag('setono_sylius_cms.problem_kind'));
        $this->setDefinition('app.channels_and_taxons', (new Definition(ChannelAndTaxonReferences::class))->addTag('setono_sylius_cms.problem_kind'));

        $this->compile();

        $listener = $this->container->getDefinition(ProblemCountListener::class);
        self::assertSame([ProductInterface::class, TaxonInterface::class, ChannelInterface::class], $listener->getArgument(2));
        self::assertSame([ProductInterface::class => ['code']], $listener->getArgument(3));
    }

    /**
     * @test
     */
    public function it_does_nothing_without_the_services_it_hands_the_kinds_to(): void
    {
        $this->setDefinition('app.kind', (new Definition(UnknownBlockType::class))->addTag('setono_sylius_cms.problem_kind', ['lint' => true]));

        $this->compile();

        $this->assertContainerBuilderNotHasService(ProblemFinder::class);
        $this->assertContainerBuilderNotHasService(LintCommand::class);
        $this->assertContainerBuilderNotHasService(ProblemCountUpdater::class);
        $this->assertContainerBuilderNotHasService(ProblemCountFingerprint::class);
        $this->assertContainerBuilderNotHasService(ProblemCountProvider::class);
        $this->assertContainerBuilderNotHasService(ProblemCountListener::class);
    }
}
