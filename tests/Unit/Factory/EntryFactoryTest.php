<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Factory;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Factory\EntryFactory;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistry;
use Setono\SyliusCMSPlugin\Targeting\ChannelsAndAudienceModifierInterface;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Landing;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Recipe;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Standard;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredLayout;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Sylius\Component\Channel\Model\Channel;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;

final class EntryFactoryTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_starts_a_new_entry_in_every_channel_with_a_code_through_the_channels_and_audience_modifier(): void
    {
        $page = new Page();
        $decorated = $this->prophesize(FactoryInterface::class);
        $decorated->createNew()->willReturn($page);

        $channels = $this->prophesize(ChannelRepositoryInterface::class);
        $channels->findAll()->willReturn([self::channel('WEB'), new Channel(), self::channel('B2B')]);

        $modifier = $this->prophesize(ChannelsAndAudienceModifierInterface::class);
        $modifier->setChannels($page, ['WEB', 'B2B'])->shouldBeCalledOnce();

        $entry = (new EntryFactory($decorated->reveal(), $channels->reveal(), $modifier->reveal(), self::layouts(), 'page'))->createNew();

        self::assertSame($page, $entry);
    }

    /**
     * @test
     */
    public function it_starts_a_new_entry_in_its_content_types_default_layout_with_the_blocks_it_has(): void
    {
        $page = new Page();
        $heading = new StoredBlock('0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f', 'heading', 1);
        $page->setBlockTree(new StoredTree(new StoredLayout('standard', 1, ['showTitle' => false]), [$heading]));
        $decorated = $this->prophesize(FactoryInterface::class);
        $decorated->createNew()->willReturn($page);

        $entry = $this->factory($decorated->reveal(), 'page')->createNew();

        $tree = $entry->getBlockTree();
        self::assertEquals(new StoredLayout('landing', 2), $tree->layout, 'The default the config names, at its version, with no values');
        self::assertEquals([$heading], $tree->blocks);
    }

    /**
     * @test
     */
    public function a_new_entry_of_a_content_type_without_layouts_has_none(): void
    {
        $recipe = new Recipe();
        $decorated = $this->prophesize(FactoryInterface::class);
        $decorated->createNew()->willReturn($recipe);

        $entry = $this->factory($decorated->reveal(), 'recipe')->createNew();

        self::assertNull($entry->getBlockTree()->layout);
    }

    /**
     * @test
     */
    public function it_needs_the_factory_it_decorates_to_create_an_entry(): void
    {
        $decorated = $this->prophesize(FactoryInterface::class);
        $decorated->createNew()->willReturn(new \stdClass());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(\stdClass::class);

        $this->factory($decorated->reveal(), 'page')->createNew();
    }

    /**
     * @template T of EntryInterface
     *
     * @param FactoryInterface<T> $decorated
     *
     * @return EntryFactory<T>
     */
    private function factory(FactoryInterface $decorated, string $contentType): EntryFactory
    {
        $channels = $this->prophesize(ChannelRepositoryInterface::class);
        $channels->findAll()->willReturn([]);

        return new EntryFactory($decorated, $channels->reveal(), $this->prophesize(ChannelsAndAudienceModifierInterface::class)->reveal(), self::layouts(), $contentType);
    }

    /**
     * Page's layouts, whose default the config changes to Landing
     */
    private static function layouts(): LayoutRegistry
    {
        return new LayoutRegistry(['page' => ['standard' => Standard::class, 'landing' => Landing::class]], new FieldReader(), ['page' => 'landing']);
    }

    private static function channel(string $code): ChannelInterface
    {
        $channel = new Channel();
        $channel->setCode($code);

        return $channel;
    }
}
