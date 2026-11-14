<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\Expect;
use Playwright\Locator\LocatorInterface;
use Playwright\Page\PageInterface;

final class StructureBlocksTest extends UITestCase
{
    /**
     * @test
     */
    public function an_admin_user_nests_blocks_in_columns_and_the_shop_lays_them_out_side_by_side_or_stacked(): void
    {
        $this->signInAsAdmin();
        $entry = self::createPage('Layout', 'layout');

        $page = $this->visit(sprintf('/admin/cms/entry/%s/builder', $entry->getUuid()));
        $tree = $page->getByRole('navigation', ['name' => 'Blocks']);

        // Columns comes with its two Columns
        $this->pick($page, $page->getByRole('button', ['name' => 'Add block', 'exact' => true]), 'Columns');
        Expect::locator($tree->getByRole('button', ['name' => 'Column', 'exact' => true]))->toHaveCount(2);

        // A heading in the first Column, from + inside it
        $this->pick($page, $tree->getByRole('button', ['name' => 'Add a block inside Column', 'exact' => true])->first(), 'Heading');
        $page->getByLabel('Text')->fill('Left');

        // A heading at the top level, dragged into the second Column
        $this->pick($page, $page->getByRole('button', ['name' => 'Add block', 'exact' => true]), 'Heading');
        $page->getByLabel('Text')->fill('Right');
        $tree->getByRole('button', ['name' => 'Heading Right'])->dragTo($tree->getByRole('button', ['name' => 'Column', 'exact' => true])->nth(1));

        $nested = ['Columns', ['Column', ['Heading Left'], 'Column', ['Heading Right']]];
        self::assertSame($nested, self::shape($tree));

        // A Column only goes inside Columns, so dropping one before Columns, at the top level, is refused
        $tree->getByRole('button', ['name' => 'Column', 'exact' => true])->first()->dragTo(
            $tree->getByRole('button', ['name' => 'Columns', 'exact' => true]),
            ['targetPosition' => ['x' => 10, 'y' => 1]],
        );

        Expect::locator($tree->getByRole('alert'))->toHaveText("Column can't go there");
        self::assertSame($nested, self::shape($tree));

        Expect::locator($page->getByRole('status'))->toHaveText('Saved');
        $page->getByRole('button', ['name' => 'Publish'])->click();
        Expect::locator($page->getByText('Published', ['exact' => true]))->toBeVisible();

        $page = $this->visit('/en_US/layout');
        $columns = $page->locator('.cms-tree > .cms-columns > .cms-column');
        Expect::locator($columns->nth(0))->toContainText('Left');
        Expect::locator($columns->nth(1))->toContainText('Right');

        $page->setViewportSize(1280, 800);
        [$left, $right] = [self::box($columns->nth(0)), self::box($columns->nth(1))];
        self::assertEqualsWithDelta($left['y'], $right['y'], 1.0, 'On a wide viewport the columns sit side by side');
        self::assertGreaterThanOrEqual($left['x'] + $left['width'], $right['x']);
        self::assertEqualsWithDelta($left['width'], $right['width'], 1.0, 'The columns are equally wide');

        $page->setViewportSize(400, 800);
        [$top, $bottom] = [self::box($columns->nth(0)), self::box($columns->nth(1))];
        self::assertEqualsWithDelta($top['x'], $bottom['x'], 1.0, 'On a narrow viewport the columns are stacked');
        self::assertGreaterThanOrEqual($top['y'] + $top['height'], $bottom['y']);
    }

    /**
     * Opens the block picker from a + button and picks a block type
     */
    private function pick(PageInterface $page, LocatorInterface $button, string $blockType): void
    {
        $button->click();
        $page->getByRole('dialog', ['name' => 'Add block'])->getByRole('button', ['name' => $blockType, 'exact' => true])->click();
    }

    /**
     * The tree's blocks as their row names, each followed by a list of the blocks inside it
     *
     * @return list<mixed>
     */
    private static function shape(LocatorInterface $tree): array
    {
        /** @var list<mixed> $shape */
        $shape = $tree->evaluate(<<<'JS'
            (nav) => {
                const shape = (list) => [...list.children].flatMap((item) => {
                    const button = item.querySelector(':scope > [draggable] button[aria-pressed]');
                    const name = [...button.querySelectorAll('span')].map((span) => span.textContent).join(' ');
                    const children = item.querySelector(':scope > ul');

                    return children === null ? [name] : [name, shape(children)];
                });

                return shape(nav.querySelector('ul'));
            }
            JS);

        return $shape;
    }

    /**
     * @return array{x: float|int, y: float|int, width: float|int, height: float|int}
     */
    private static function box(LocatorInterface $locator): array
    {
        $box = $locator->boundingBox();
        self::assertNotNull($box);

        return $box;
    }
}
