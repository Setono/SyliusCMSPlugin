<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Registry;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistry;
use Setono\SyliusCMSPlugin\Registry\UnknownLayoutException;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Landing;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Plain;

final class LayoutRegistryTest extends TestCase
{
    /**
     * @test
     */
    public function it_reads_a_layout_of_a_content_type_from_its_attribute_and_its_fields(): void
    {
        $registry = new LayoutRegistry(['page' => ['landing' => Landing::class]], new FieldReader());

        $layout = $registry->get('page', 'landing');

        self::assertSame('landing', $layout->code);
        self::assertSame('page', $layout->contentType);
        self::assertSame(2, $layout->version);
        self::assertSame(Landing::class, $layout->class);
        self::assertSame(['transparentHeader'], array_keys($layout->fields));
        self::assertSame($layout, $registry->get('page', 'landing'), 'It reads each layout once');
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
    public function it_throws_on_an_unknown_layout(): void
    {
        $this->expectException(UnknownLayoutException::class);
        $this->expectExceptionMessage('The content type "blog_post" has no layout with the code "landing"');

        (new LayoutRegistry(['page' => ['landing' => Landing::class]], new FieldReader()))->get('blog_post', 'landing');
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
}
