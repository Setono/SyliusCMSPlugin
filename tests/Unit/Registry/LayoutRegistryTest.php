<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Registry;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Registry\LayoutMetadata;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistry;
use Setono\SyliusCMSPlugin\Registry\UnknownLayoutException;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Landing;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Plain;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Standard;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Wide;

final class LayoutRegistryTest extends TestCase
{
    /**
     * @test
     */
    public function it_reads_a_layout_of_a_content_type_from_its_attribute_and_its_fields(): void
    {
        $registry = new LayoutRegistry(['page' => ['standard' => Standard::class, 'landing' => Landing::class]], new FieldReader());

        $layout = $registry->get('page', 'standard');

        self::assertSame('standard', $layout->code);
        self::assertSame('page', $layout->contentType);
        self::assertSame(1, $layout->version);
        self::assertSame(Standard::class, $layout->class);
        self::assertSame(['showTitle'], array_keys($layout->fields));
        self::assertSame('app.standard', $layout->label);
        self::assertSame('standard.html.twig', $layout->template);
        self::assertSame('/build/app/standard.svg', $layout->thumbnail);
        self::assertSame(['title'], $layout->shows);
        self::assertFalse($layout->disabled);
        self::assertSame($layout, $registry->get('page', 'standard'), 'It reads each layout once');

        $landing = $registry->get('page', 'landing');
        self::assertSame(2, $landing->version);
        self::assertNull($landing->thumbnail);
        self::assertSame([], $landing->shows);
    }

    /**
     * @test
     */
    public function it_knows_which_layouts_each_content_type_has(): void
    {
        $registry = new LayoutRegistry(['page' => ['landing' => Landing::class]], new FieldReader());

        self::assertTrue($registry->has('page', 'landing'));
        self::assertFalse($registry->has('page', 'standard'));
        self::assertFalse($registry->has('blog_post', 'landing'), 'Layout codes are unique only within a content type');
    }

    /**
     * @test
     */
    public function it_lists_the_layouts_of_a_content_type_the_default_first_and_then_in_the_order_they_were_registered(): void
    {
        $classes = ['page' => ['wide' => Wide::class, 'landing' => Landing::class, 'standard' => Standard::class]];

        self::assertSame(['standard', 'wide', 'landing'], self::codes((new LayoutRegistry($classes, new FieldReader(), ['page' => 'standard'], ['page.landing']))->all('page')), 'A disabled layout is listed too');
        self::assertSame(['landing', 'wide', 'standard'], self::codes((new LayoutRegistry($classes, new FieldReader(), ['page' => 'landing']))->all('page')));
        self::assertSame(['wide', 'landing', 'standard'], self::codes((new LayoutRegistry($classes, new FieldReader(), ['page' => 'wide']))->all('page')));
        self::assertSame([], (new LayoutRegistry($classes, new FieldReader(), ['page' => 'wide']))->all('recipe'));
    }

    /**
     * @test
     */
    public function the_default_layout_is_the_one_marked_as_the_default(): void
    {
        $registry = new LayoutRegistry(['page' => ['landing' => Landing::class, 'standard' => Standard::class]], new FieldReader());

        self::assertSame('standard', $registry->getDefault('page')->code);
    }

    /**
     * @test
     */
    public function a_default_layout_named_in_the_config_wins_over_the_ones_marked_as_the_default(): void
    {
        $registry = new LayoutRegistry(['page' => ['standard' => Standard::class, 'wide' => Wide::class, 'landing' => Landing::class]], new FieldReader(), ['page' => 'landing']);

        self::assertSame('landing', $registry->getDefault('page')->code);
        self::assertSame('landing', $registry->resolve('page', null)?->code);
    }

    /**
     * @test
     */
    public function two_layouts_marked_as_the_default_of_one_content_type_are_refused(): void
    {
        $registry = new LayoutRegistry(['page' => ['standard' => Standard::class, 'wide' => Wide::class]], new FieldReader());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf('The content type "page" has more than one default layout: %s and %s. Remove default: true from all but one, or name one in setono_sylius_cms.content_types.page.default_layout', Standard::class, Wide::class));

        $registry->getDefault('page');
    }

    /**
     * @test
     */
    public function a_content_type_whose_layouts_are_none_of_them_the_default_is_refused(): void
    {
        $registry = new LayoutRegistry(['page' => ['landing' => Landing::class]], new FieldReader());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The content type "page" has no default layout. Mark one of its layouts #[Layout(default: true)], or name one in setono_sylius_cms.content_types.page.default_layout');

        $registry->getDefault('page');
    }

    /**
     * @test
     */
    public function a_default_layout_named_in_the_config_must_be_one_of_the_content_types(): void
    {
        $registry = new LayoutRegistry(['page' => ['standard' => Standard::class]], new FieldReader(), ['page' => 'landing']);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('setono_sylius_cms.content_types.page.default_layout names the layout "landing", which the content type "page" doesn\'t have');

        $registry->getDefault('page');
    }

    /**
     * @test
     */
    public function the_default_layout_cant_be_disabled(): void
    {
        $registry = new LayoutRegistry(['page' => ['standard' => Standard::class, 'landing' => Landing::class]], new FieldReader(), ['page' => 'landing'], ['page.landing']);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The layout "landing" is the default layout of the content type "page", so it can\'t be disabled in setono_sylius_cms.disabled.layouts');

        $registry->getDefault('page');
    }

    /**
     * @test
     */
    public function a_content_type_without_layouts_has_no_default(): void
    {
        $this->expectException(UnknownLayoutException::class);
        $this->expectExceptionMessage('The content type "recipe" has no layouts');

        (new LayoutRegistry(['page' => ['standard' => Standard::class]], new FieldReader()))->getDefault('recipe');
    }

    /**
     * @test
     */
    public function a_disabled_layout_is_known_but_flagged(): void
    {
        $registry = new LayoutRegistry(['page' => ['standard' => Standard::class, 'landing' => Landing::class]], new FieldReader(), disabled: ['page.landing', 'blog_post.standard']);

        self::assertTrue($registry->has('page', 'landing'));
        self::assertTrue($registry->get('page', 'landing')->disabled);
        self::assertFalse($registry->get('page', 'standard')->disabled, 'A layout is disabled for its own content type only');
    }

    /**
     * @test
     */
    public function an_entry_renders_in_its_own_layout_unless_it_was_removed_or_disabled_and_then_in_the_default(): void
    {
        $registry = new LayoutRegistry(['page' => ['standard' => Standard::class, 'landing' => Landing::class, 'wide' => Wide::class]], new FieldReader(), ['page' => 'standard'], ['page.wide']);

        self::assertSame('landing', $registry->resolve('page', 'landing')?->code);
        self::assertSame('standard', $registry->resolve('page', 'gone')?->code, 'A layout removed from code');
        self::assertSame('standard', $registry->resolve('page', 'wide')?->code, 'A disabled layout');
        self::assertSame('standard', $registry->resolve('page', null)?->code, 'A tree without a layout');
        self::assertNull($registry->resolve('recipe', null), 'A content type without layouts');
        self::assertNull($registry->resolve('recipe', 'standard'));
    }

    /**
     * @test
     */
    public function it_throws_on_an_unknown_layout(): void
    {
        $this->expectException(UnknownLayoutException::class);
        $this->expectExceptionMessage('The content type "blog_post" has no layout with the code "landing"');

        (new LayoutRegistry(['page' => ['standard' => Standard::class]], new FieldReader()))->get('blog_post', 'landing');
    }

    /**
     * @test
     */
    public function it_throws_when_a_class_has_no_layout_attribute(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf('The class %s must carry #[Layout]', Plain::class));

        (new LayoutRegistry(['page' => ['plain' => Plain::class]], new FieldReader()))->get('page', 'plain');
    }

    /**
     * @param list<LayoutMetadata> $layouts
     *
     * @return list<string>
     */
    private static function codes(array $layouts): array
    {
        return array_map(static fn (LayoutMetadata $layout): string => $layout->code, $layouts);
    }
}
