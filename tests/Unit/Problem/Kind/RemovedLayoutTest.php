<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Problem\Kind;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Problem\Fix;
use Setono\SyliusCMSPlugin\Problem\Kind\RemovedLayout;
use Setono\SyliusCMSPlugin\Problem\Problem;
use Setono\SyliusCMSPlugin\Problem\Severity;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistry;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Landing;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Recipe;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Standard;
use Setono\SyliusCMSPlugin\Tree\StoredLayout;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Symfony\Component\Translation\TranslatableMessage;

final class RemovedLayoutTest extends TestCase
{
    /**
     * @test
     */
    public function it_finds_a_layout_the_code_no_longer_has_as_a_warning_fixed_in_the_builders_entry_settings(): void
    {
        $page = self::page(new StoredLayout('gone', 1, ['transparentHeader' => true]));

        self::assertEquals([new Problem(
            'removed_layout',
            Severity::Warning,
            $page,
            new TranslatableMessage('setono_sylius_cms.problem.removed_layout', ['%layout%' => 'gone', '%default%' => new TranslatableMessage('app.standard')]),
            Fix::inBuilder($page),
        )], [...self::kind()->findIn($page)]);
    }

    /**
     * @test
     */
    public function it_finds_a_disabled_layout(): void
    {
        $page = self::page(new StoredLayout('landing', 2));

        $problems = [...self::kind(['page.landing'])->findIn($page)];

        self::assertCount(1, $problems);
        self::assertEquals(new TranslatableMessage('setono_sylius_cms.problem.removed_layout', ['%layout%' => 'landing', '%default%' => new TranslatableMessage('app.standard')]), $problems[0]->message);
    }

    /**
     * @test
     */
    public function it_finds_nothing_in_a_layout_the_code_has_in_a_tree_without_a_layout_or_in_a_content_type_without_layouts(): void
    {
        $recipe = new Recipe();
        $recipe->setBlockTree(new StoredTree(new StoredLayout('gone', 1), []));

        self::assertSame([], [...self::kind()->findIn(self::page(new StoredLayout('landing', 2)))]);
        self::assertSame([], [...self::kind()->findIn(self::page(new StoredLayout('standard', 1)))]);
        self::assertSame([], [...self::kind()->findIn(self::page(null))]);
        self::assertSame([], [...self::kind()->findIn($recipe)]);
    }

    private static function page(?StoredLayout $layout): Page
    {
        $page = new Page();
        $page->setBlockTree(new StoredTree($layout, []));

        return $page;
    }

    /**
     * @param list<string> $disabled
     */
    private static function kind(array $disabled = []): RemovedLayout
    {
        return new RemovedLayout(
            new LayoutRegistry(['page' => ['standard' => Standard::class, 'landing' => Landing::class]], new FieldReader(), disabled: $disabled),
            new ContentTypeRegistry([
                'page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page'],
                'recipe' => ['model' => Recipe::class, 'translation' => null, 'resource' => 'app.recipe'],
            ]),
        );
    }
}
