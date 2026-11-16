<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Field;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Field\HydrationContext;
use Setono\SyliusCMSPlugin\Field\Media;
use Setono\SyliusCMSPlugin\Field\MediaHandler;
use Setono\SyliusCMSPlugin\Field\Reference;
use Setono\SyliusCMSPlugin\Media\MediaKind;
use Setono\SyliusCMSPlugin\Model\MediaItem;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Rendering\ReferenceMap;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Tests\Unit\Field\Fixture\WithMedia;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

final class MediaHandlerTest extends TestCase
{
    use ProphecyTrait;

    private const UUID = '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e';

    /**
     * @test
     */
    public function it_is_the_shipped_media_field_type_shared_by_all_translations_by_default(): void
    {
        $fields = (new FieldReader())->read(WithMedia::class);

        self::assertSame('media', $fields['image']->type->code);
        self::assertFalse($fields['image']->translatable);
        self::assertSame(MediaHandler::class, $fields['image']->handler());
        self::assertInstanceOf(Media::class, $fields['image']->field);
        self::assertSame(['image'], $fields['image']->field->kinds);
    }

    /**
     * @test
     */
    public function it_takes_images_unless_it_names_other_kinds(): void
    {
        self::assertSame([MediaKind::Image], (new Media(label: 'Image'))->mediaKinds());
        self::assertSame([MediaKind::Video, MediaKind::Image], (new Media(label: 'Video', kinds: ['video', 'image']))->mediaKinds());
    }

    /**
     * @test
     */
    public function it_refuses_a_kind_that_is_not_a_media_kind(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        /** @phpstan-ignore argument.type */
        new Media(label: 'Image', kinds: ['picture']);
    }

    /**
     * @test
     */
    public function it_refuses_to_take_no_kind(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Media(label: 'Image', kinds: []);
    }

    /**
     * @test
     */
    public function it_describes_a_media_item_uuid_with_its_kinds_in_its_schema(): void
    {
        self::assertSame(
            ['type' => 'string', 'format' => 'uuid', 'x-cms-options' => ['kinds' => ['image', 'video']]],
            (new MediaHandler())->schema(new Media(label: 'Image', kinds: ['image', 'video'])),
        );
    }

    /**
     * @test
     */
    public function it_hydrates_a_uuid_as_the_media_item_the_reference_map_holds(): void
    {
        $mediaItem = self::mediaItem(MediaKind::Image);

        self::assertSame($mediaItem, (new MediaHandler())->hydrate(new Media(label: 'Image'), self::UUID, $this->hydrationContext([self::UUID => $mediaItem])));
    }

    /**
     * @test
     */
    public function it_hydrates_a_uuid_in_capitals_as_the_media_item_too(): void
    {
        $mediaItem = self::mediaItem(MediaKind::Image);

        self::assertSame($mediaItem, (new MediaHandler())->hydrate(new Media(label: 'Image'), strtoupper(self::UUID), $this->hydrationContext([self::UUID => $mediaItem])));
    }

    /**
     * @test
     */
    public function it_hydrates_a_broken_reference_as_missing(): void
    {
        self::assertNull((new MediaHandler())->hydrate(new Media(label: 'Image'), self::UUID, $this->hydrationContext([])));
    }

    /**
     * @test
     */
    public function it_hydrates_a_media_item_of_a_kind_the_field_does_not_take_as_missing(): void
    {
        $context = $this->hydrationContext([self::UUID => self::mediaItem(MediaKind::Video)]);

        self::assertNull((new MediaHandler())->hydrate(new Media(label: 'Image'), self::UUID, $context));
    }

    /**
     * @test
     */
    public function it_hydrates_a_value_that_does_not_fit_as_missing(): void
    {
        $context = $this->hydrationContext([self::UUID => self::mediaItem(MediaKind::Image)]);
        $handler = new MediaHandler();

        self::assertNull($handler->hydrate(new Media(label: 'Image'), 'not-a-uuid', $context));
        self::assertNull($handler->hydrate(new Media(label: 'Image'), [self::UUID], $context));
    }

    /**
     * @test
     */
    public function it_fits_a_uuid_and_nothing_else(): void
    {
        $handler = new MediaHandler();

        self::assertTrue($handler->fits(new Media(label: 'Image'), self::UUID));
        self::assertFalse($handler->fits(new Media(label: 'Image'), 'a-photo.jpg'));
        self::assertFalse($handler->fits(new Media(label: 'Image'), 7));
        self::assertFalse($handler->fits(new Media(label: 'Image'), [self::UUID]));
    }

    /**
     * @test
     */
    public function it_refers_to_its_media_item_as_a_dependency(): void
    {
        self::assertEquals(
            [new Reference('media_item', self::UUID, true)],
            [...(new MediaHandler())->references(new Media(label: 'Image'), strtoupper(self::UUID))],
        );
    }

    /**
     * @test
     */
    public function it_refers_to_nothing_when_the_value_does_not_fit(): void
    {
        self::assertSame([], [...(new MediaHandler())->references(new Media(label: 'Image'), 'a-photo.jpg')]);
        self::assertSame([], [...(new MediaHandler())->references(new Media(label: 'Image'), null)]);
    }

    /**
     * @test
     */
    public function it_accepts_a_uuid_and_a_missing_value(): void
    {
        $context = $this->prophesize(ExecutionContextInterface::class);
        $context->buildViolation(Argument::cetera())->shouldNotBeCalled();

        $handler = new MediaHandler();
        $handler->validate(new Media(label: 'Image'), self::UUID, $context->reveal());
        $handler->validate(new Media(label: 'Image'), null, $context->reveal());
    }

    /**
     * @test
     */
    public function it_rejects_a_value_that_is_not_a_uuid(): void
    {
        $builder = $this->prophesize(ConstraintViolationBuilderInterface::class);
        $builder->addViolation()->shouldBeCalledOnce();

        $context = $this->prophesize(ExecutionContextInterface::class);
        $context->buildViolation('setono_sylius_cms.field.not_media_item')->willReturn($builder->reveal())->shouldBeCalledOnce();

        (new MediaHandler())->validate(new Media(label: 'Image'), 'a-photo.jpg', $context->reveal());
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
            new RenderContext($this->prophesize(ChannelInterface::class)->reveal(), 'en_US', 'USD', null, '@guest', new \DateTimeImmutable()),
            new ReferenceMap(['media_item' => $mediaItems]),
        );
    }
}
