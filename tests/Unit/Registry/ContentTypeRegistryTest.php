<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Registry;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Attribute\Allow;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\UnknownContentTypeException;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Recipe;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Untagged;

final class ContentTypeRegistryTest extends TestCase
{
    private const PAGE = ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page'];

    /**
     * @test
     */
    public function it_reads_a_content_type_from_its_attribute_and_its_resource(): void
    {
        $page = (new ContentTypeRegistry(['page' => self::PAGE]))->get('page');

        self::assertSame('page', $page->code);
        self::assertSame('setono_sylius_cms.content_type.page', $page->label);
        self::assertSame(1, $page->version);
        self::assertSame('', $page->prefix);
        self::assertEquals([new Allow('*')], $page->blocks);
        self::assertSame(Page::class, $page->modelClass);
        self::assertSame(PageTranslation::class, $page->translationModelClass);
        self::assertSame('setono_sylius_cms.page', $page->resourceAlias);
        self::assertTrue($page->hasOwnUrl());
    }

    /**
     * @test
     */
    public function it_finds_a_content_type_by_an_entry_of_it_or_of_a_subclass(): void
    {
        $registry = new ContentTypeRegistry(['page' => self::PAGE]);

        self::assertSame('page', $registry->getByEntry(new Page())->code);
        self::assertSame('page', $registry->getByEntry(new class() extends Page {
        })->code);
    }

    /**
     * @test
     */
    public function it_reads_each_content_type_once(): void
    {
        $registry = new ContentTypeRegistry(['page' => self::PAGE]);

        self::assertSame($registry->get('page'), $registry->get('page'));
    }

    /**
     * @test
     */
    public function it_finds_the_content_type_whose_model_the_entry_is(): void
    {
        $registry = new ContentTypeRegistry([
            'recipe' => ['model' => Recipe::class, 'translation' => null, 'resource' => 'app.recipe'],
            'page' => self::PAGE,
        ]);

        self::assertSame('page', $registry->getByEntry(new Page())->code);
        self::assertSame('recipe', $registry->getByEntry(new Recipe())->code);
        self::assertFalse($registry->get('recipe')->hasOwnUrl());
        self::assertNull($registry->get('recipe')->blocks);
    }

    /**
     * @test
     */
    public function it_throws_when_a_model_has_no_content_type_attribute(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('#[ContentType]');

        (new ContentTypeRegistry(['untagged' => ['model' => Untagged::class, 'translation' => null, 'resource' => 'app.untagged']]))->get('untagged');
    }

    /**
     * @test
     */
    public function it_lists_every_content_type(): void
    {
        $registry = new ContentTypeRegistry(['page' => self::PAGE]);

        self::assertTrue($registry->has('page'));
        self::assertFalse($registry->has('snippet'));
        self::assertSame(['page'], array_map(static fn ($contentType) => $contentType->code, $registry->all()));
    }

    /**
     * @test
     */
    public function it_throws_on_an_unknown_content_type(): void
    {
        $this->expectException(UnknownContentTypeException::class);

        (new ContentTypeRegistry([]))->get('snippet');
    }

    /**
     * @test
     */
    public function it_throws_on_an_entry_of_no_registered_content_type(): void
    {
        $this->expectException(UnknownContentTypeException::class);

        (new ContentTypeRegistry([]))->getByEntry(new Page());
    }
}
