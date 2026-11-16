<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Problem\Kind;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Block\Group;
use Setono\SyliusCMSPlugin\Block\Heading;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Problem\Fix;
use Setono\SyliusCMSPlugin\Problem\Kind\DeletedCustomerGroup;
use Setono\SyliusCMSPlugin\Problem\Problem;
use Setono\SyliusCMSPlugin\Problem\Severity;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Targeting\Audience;
use Setono\SyliusCMSPlugin\Tests\Support\ChannelsAndAudience;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Sylius\Component\Customer\Model\CustomerGroup;
use Sylius\Component\Customer\Model\CustomerGroupInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Component\Translation\TranslatableMessage;

final class DeletedCustomerGroupTest extends TestCase
{
    use ProphecyTrait;

    private const A = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4a';

    private const B = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4b';

    private const C = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4c';

    /**
     * @test
     */
    public function it_finds_a_deleted_group_in_a_customer_group_condition_at_any_depth_as_an_error_fixed_in_the_builder_at_the_block(): void
    {
        $page = self::page(
            self::block(self::A, 'heading', [self::groups(['wholesale', 'gone'], negate: true)]),
            self::block(self::B, 'group', [], [
                self::block(self::C, 'removed', [self::groups(['retail'], kind: 'channel'), self::groups(['retail']), self::groups(['@guest', 'old', 42, 'gone'])]),
            ]),
        );

        self::assertEquals([
            self::inBlock($page, self::A, 'gone', new TranslatableMessage('setono_sylius_cms.block_type.heading')),
            self::inBlock($page, self::C, 'old', 'removed'),
            self::inBlock($page, self::C, 'gone', 'removed'),
        ], [...$this->kind()->findIn($page)]);
    }

    /**
     * @test
     */
    public function it_finds_a_deleted_group_in_the_audience_fixed_in_the_builders_settings(): void
    {
        $page = self::page();
        ChannelsAndAudience::modifier()->setAudience($page, Audience::except('@no_group', 'gone', 'wholesale'));

        self::assertEquals([new Problem(
            'deleted_customer_group',
            Severity::Error,
            $page,
            new TranslatableMessage('setono_sylius_cms.problem.deleted_customer_group_in_audience', ['%group%' => 'gone']),
            Fix::inBuilder($page),
        )], [...$this->kind()->findIn($page)]);
    }

    /**
     * @test
     */
    public function it_finds_nothing_when_every_group_exists_or_is_guests_or_customers_without_a_group(): void
    {
        $page = self::page(
            self::block(self::A, 'heading', [self::groups(['wholesale', '@guest', '@no_group']), self::groups(['wholesale'], kind: 'channel')]),
            self::block(self::B, 'heading', [['kind' => 'customer_group', 'negate' => false, 'params' => ['groups' => 'gone']], ['kind' => 'customer_group', 'negate' => false, 'params' => 'gone'], ['kind' => 'customer_group', 'negate' => false]]),
        );
        ChannelsAndAudience::modifier()->setAudience($page, Audience::only('@guest', 'retail'));

        self::assertSame([], [...$this->kind()->findIn($page)]);
    }

    /**
     * The store's groups are read once, until the kind is reset, and only when there's a group to look up
     *
     * @test
     */
    public function it_reads_the_stores_groups_once_until_reset(): void
    {
        $repository = $this->repository();
        $repository->findAll()->shouldBeCalledTimes(2);
        $kind = new DeletedCustomerGroup($repository->reveal(), self::blockTypes());

        [...$kind->findIn(self::page())];
        [...$kind->findIn(self::page(self::block(self::A, 'heading', [self::groups(['gone'])])))];
        [...$kind->findIn(self::page(self::block(self::A, 'heading', [self::groups(['gone'])])))];
        $kind->reset();
        self::assertCount(1, [...$kind->findIn(self::page(self::block(self::A, 'heading', [self::groups(['gone', 'gone'])])))]);
    }

    /**
     * @param string|TranslatableMessage $block the block type's label, or the type of a block the code no longer has
     */
    private static function inBlock(Page $page, string $key, string $group, string|TranslatableMessage $block): Problem
    {
        return new Problem(
            'deleted_customer_group',
            Severity::Error,
            $page,
            new TranslatableMessage('setono_sylius_cms.problem.deleted_customer_group', ['%group%' => $group, '%block%' => $block]),
            Fix::inBuilder($page, $key),
            $key,
        );
    }

    /**
     * @param list<mixed> $groups
     *
     * @return array<string, mixed>
     */
    private static function groups(array $groups, bool $negate = false, string $kind = 'customer_group'): array
    {
        return ['kind' => $kind, 'negate' => $negate, 'params' => ['groups' => $groups]];
    }

    /**
     * @param list<array<string, mixed>> $conditions
     * @param list<StoredBlock> $children
     */
    private static function block(string $key, string $type, array $conditions, array $children = []): StoredBlock
    {
        return new StoredBlock($key, $type, 1, conditions: $conditions, children: $children);
    }

    private static function page(StoredBlock ...$blocks): Page
    {
        $page = new Page();
        $page->setBlockTree(new StoredTree(null, array_values($blocks)));

        return $page;
    }

    private function kind(): DeletedCustomerGroup
    {
        return new DeletedCustomerGroup($this->repository()->reveal(), self::blockTypes());
    }

    /**
     * @return \Prophecy\Prophecy\ObjectProphecy<RepositoryInterface<CustomerGroupInterface>>
     */
    private function repository(): \Prophecy\Prophecy\ObjectProphecy
    {
        $groups = [];
        foreach (['retail', 'wholesale'] as $code) {
            $group = new CustomerGroup();
            $group->setCode($code);
            $groups[] = $group;
        }

        /** @var \Prophecy\Prophecy\ObjectProphecy<RepositoryInterface<CustomerGroupInterface>> $repository */
        $repository = $this->prophesize(RepositoryInterface::class);
        $repository->findAll()->willReturn($groups);

        return $repository;
    }

    private static function blockTypes(): BlockTypeRegistry
    {
        return new BlockTypeRegistry(['heading' => Heading::class, 'group' => Group::class], new FieldReader());
    }
}
