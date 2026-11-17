<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Field;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Field\HydrationContext;
use Setono\SyliusCMSPlugin\Field\MediaList;
use Setono\SyliusCMSPlugin\Field\MediaListHandler;
use Setono\SyliusCMSPlugin\Field\Reference;
use Setono\SyliusCMSPlugin\Media\MediaKind;
use Setono\SyliusCMSPlugin\Model\MediaItem;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Rendering\ReferenceMap;
use Setono\SyliusCMSPlugin\Tests\Support\Rendering;
use Setono\SyliusCMSPlugin\Tests\Unit\Field\Fixture\WithMedia;
use Setono\SyliusCMSPlugin\Value\ItemList;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

final class MediaListHandlerTest extends TestCase
{
    use ProphecyTrait;

    private const FIRST = '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d01';

    private const SECOND = '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d02';

    private const DELETED = '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d03';

    /**
     * @test
     */
    public function it_is_the_shipped_media_list_field_type_shared_by_all_translations_by_default(): void
    {
        $fields = (new FieldReader())->read(WithMedia::class);

        self::assertSame('media_list', $fields['documents']->type->code);
        self::assertFalse($fields['documents']->translatable);
        self::assertSame(MediaListHandler::class, $fields['documents']->handler());
        self::assertInstanceOf(MediaList::class, $fields['documents']->field);
        self::assertSame(['document'], $fields['documents']->field->kinds);
        self::assertSame(3, $fields['documents']->field->max);
    }

    /**
     * @test
     */
    public function it_takes_images_with_no_most_unless_it_says_otherwise(): void
    {
        $field = new MediaList(label: 'Gallery');

        self::assertSame([MediaKind::Image], $field->mediaKinds());
        self::assertNull($field->max);
    }

    /**
     * @test
     */
    public function it_refuses_a_most_below_one(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new MediaList(label: 'Gallery', max: 0);
    }

    /**
     * @test
     */
    public function it_refuses_a_kind_that_is_not_a_media_kind(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        /** @phpstan-ignore argument.type */
        new MediaList(label: 'Gallery', kinds: ['audio']);
    }

    /**
     * @test
     */
    public function it_describes_a_list_of_media_item_uuids_with_its_kinds_and_most_in_its_schema(): void
    {
        self::assertSame(
            [
                'type' => 'array',
                'items' => ['type' => 'string', 'format' => 'uuid'],
                'maxItems' => 3,
                'x-cms-options' => ['kinds' => ['document'], 'max' => 3],
            ],
            (new MediaListHandler())->schema(new MediaList(label: 'Documents', kinds: ['document'], max: 3)),
        );
    }

    /**
     * @test
     */
    public function it_describes_a_list_without_a_most(): void
    {
        self::assertSame(
            [
                'type' => 'array',
                'items' => ['type' => 'string', 'format' => 'uuid'],
                'x-cms-options' => ['kinds' => ['image'], 'max' => null],
            ],
            (new MediaListHandler())->schema(new MediaList(label: 'Gallery')),
        );
    }

    /**
     * @test
     */
    public function it_hydrates_the_uuids_as_a_list_of_their_media_items_in_their_order(): void
    {
        $first = self::mediaItem(MediaKind::Image);
        $second = self::mediaItem(MediaKind::Image);

        $list = (new MediaListHandler())->hydrate(new MediaList(label: 'Gallery'), [self::SECOND, self::FIRST], $this->hydrationContext([self::FIRST => $first, self::SECOND => $second]));

        self::assertInstanceOf(ItemList::class, $list);
        self::assertSame([$second, $first], [...$list]);
    }

    /**
     * @test
     */
    public function it_leaves_broken_references_and_media_items_of_other_kinds_out(): void
    {
        $first = self::mediaItem(MediaKind::Image);
        $video = self::mediaItem(MediaKind::Video);

        $list = (new MediaListHandler())->hydrate(
            new MediaList(label: 'Gallery'),
            [self::FIRST, self::DELETED, self::SECOND],
            $this->hydrationContext([self::FIRST => $first, self::SECOND => $video]),
        );

        self::assertInstanceOf(ItemList::class, $list);
        self::assertSame([$first], [...$list]);
        self::assertCount(1, $list);
    }

    /**
     * @test
     */
    public function it_hydrates_a_list_whose_references_are_all_broken_as_an_empty_list(): void
    {
        $list = (new MediaListHandler())->hydrate(new MediaList(label: 'Gallery'), [self::DELETED], $this->hydrationContext([]));

        self::assertInstanceOf(ItemList::class, $list);
        self::assertCount(0, $list);
    }

    /**
     * @test
     */
    public function it_hydrates_a_value_that_does_not_fit_as_missing(): void
    {
        $handler = new MediaListHandler();
        $context = $this->hydrationContext([self::FIRST => self::mediaItem(MediaKind::Image)]);

        self::assertNull($handler->hydrate(new MediaList(label: 'Gallery'), self::FIRST, $context));
        self::assertNull($handler->hydrate(new MediaList(label: 'Gallery'), [self::FIRST, 'a-photo.jpg'], $context));
        self::assertNull($handler->hydrate(new MediaList(label: 'Gallery'), ['a' => self::FIRST], $context));
    }

    /**
     * @test
     */
    public function it_fits_a_list_of_uuids_even_one_longer_than_its_most(): void
    {
        $handler = new MediaListHandler();
        $field = new MediaList(label: 'Gallery', max: 1);

        self::assertTrue($handler->fits($field, [self::FIRST, self::SECOND]));
        self::assertFalse($handler->fits($field, self::FIRST));
        self::assertFalse($handler->fits($field, [self::FIRST, 7]));
        self::assertFalse($handler->fits($field, ['a' => self::FIRST]));
    }

    /**
     * @test
     */
    public function it_refers_to_each_media_item_once_as_a_dependency(): void
    {
        self::assertEquals(
            [new Reference('media_item', self::FIRST, true), new Reference('media_item', self::SECOND, true)],
            [...(new MediaListHandler())->references(new MediaList(label: 'Gallery'), [self::FIRST, strtoupper(self::SECOND), self::FIRST, 'a-photo.jpg'])],
        );
    }

    /**
     * @test
     */
    public function it_refers_to_nothing_when_the_value_is_not_a_list(): void
    {
        self::assertSame([], [...(new MediaListHandler())->references(new MediaList(label: 'Gallery'), self::FIRST)]);
    }

    /**
     * @test
     */
    public function it_accepts_a_list_of_uuids_up_to_its_most_and_a_missing_value(): void
    {
        $context = $this->prophesize(ExecutionContextInterface::class);
        $context->buildViolation(Argument::cetera())->shouldNotBeCalled();

        $handler = new MediaListHandler();
        $field = new MediaList(label: 'Gallery', max: 2);
        $handler->validate($field, [self::FIRST, self::SECOND], $context->reveal());
        $handler->validate($field, [], $context->reveal());
        $handler->validate($field, null, $context->reveal());
    }

    /**
     * @test
     */
    public function it_rejects_a_value_that_is_not_a_list_of_uuids(): void
    {
        $builder = $this->prophesize(ConstraintViolationBuilderInterface::class);
        $builder->addViolation()->shouldBeCalledOnce();

        $context = $this->prophesize(ExecutionContextInterface::class);
        $context->buildViolation('setono_sylius_cms.field.not_media_list')->willReturn($builder->reveal())->shouldBeCalledOnce();

        (new MediaListHandler())->validate(new MediaList(label: 'Gallery'), [self::FIRST, 'a-photo.jpg'], $context->reveal());
    }

    /**
     * @test
     */
    public function it_rejects_more_media_items_than_its_most(): void
    {
        $builder = $this->prophesize(ConstraintViolationBuilderInterface::class);
        $builder->setParameter('{{ limit }}', '1')->willReturn($builder->reveal())->shouldBeCalledOnce();
        $builder->setPlural(1)->willReturn($builder->reveal())->shouldBeCalledOnce();
        $builder->addViolation()->shouldBeCalledOnce();

        $context = $this->prophesize(ExecutionContextInterface::class);
        $context->buildViolation('setono_sylius_cms.field.too_many_media_items')->willReturn($builder->reveal())->shouldBeCalledOnce();

        (new MediaListHandler())->validate(new MediaList(label: 'Gallery', max: 1), [self::FIRST, self::SECOND], $context->reveal());
    }

    private static function mediaItem(MediaKind $kind): MediaItemInterface
    {
        $mediaItem = new MediaItem();
        $mediaItem->setKind($kind);

        return $mediaItem;
    }

    /**
     * @param array<string, object> $mediaItems by UUID
     */
    private function hydrationContext(array $mediaItems): HydrationContext
    {
        return new HydrationContext(
            Rendering::context(),
            new ReferenceMap(['media_item' => $mediaItems]),
        );
    }
}
