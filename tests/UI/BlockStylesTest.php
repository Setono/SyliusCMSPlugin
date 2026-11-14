<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\Expect;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;

/**
 * Block styles: an editor picks a named look for a block in the right pane, and the shop shows it.
 * See "Block styles" in docs/spec/blocks.md
 */
final class BlockStylesTest extends UITestCase
{
    /**
     * @test
     */
    public function an_admin_user_picks_the_dark_style_for_a_group_publishes_and_sees_the_dark_background_in_the_shop(): void
    {
        $this->signInAsAdmin();
        $entry = self::createPageWithBlocks('Night', 'night', [
            new StoredBlock('0193a5be-0000-7000-8000-000000000001', 'group', 1, children: [self::heading('0193a5be-0000-7000-8000-000000000002', 'After dark')]),
        ]);

        $page = $this->visit(sprintf('/admin/cms/entry/%s/builder', $entry->getUuid()));
        $preview = $page->frameLocator('iframe[title="Preview"]');
        Expect::locator($preview->locator('[data-cms-bridge]'))->toBeAttached();

        $page->getByRole('navigation', ['name' => 'Blocks'])->getByRole('button', ['name' => 'Group', 'exact' => true])->click();
        $style = $page->getByRole('combobox', ['name' => 'Style']);
        Expect::locator($style)->toHaveValue('');

        $style->selectOption('dark');

        // The preview is patched with the style, before it's published
        Expect::locator($preview->locator('.cms-tree > .cms-group'))->toHaveClass('cms-group cms-group--dark');
        Expect::locator($page->getByRole('status'))->toHaveText('Saved');
        $page->getByRole('button', ['name' => 'Publish'])->click();
        Expect::locator($page->getByText('Published', ['exact' => true]))->toBeVisible();

        $shop = $this->visit('/en_US/night');
        $group = $shop->locator('.cms-tree > .cms-group');
        Expect::locator($group)->toHaveClass('cms-group cms-group--dark');
        Expect::locator($group)->toHaveCSS('background-color', 'rgb(27, 28, 29)');
        Expect::locator($group)->toHaveCSS('color', 'rgb(255, 255, 255)');
        Expect::locator($group->getByRole('heading', ['name' => 'After dark']))->toBeVisible();
    }

    /**
     * @test
     */
    public function a_group_with_no_style_has_no_background_in_the_shop(): void
    {
        self::createPageWithBlocks('Plain', 'plain', [
            new StoredBlock('0193a5be-0000-7000-8000-000000000001', 'group', 1, children: [self::heading('0193a5be-0000-7000-8000-000000000002', 'In daylight')]),
        ], published: true);

        $shop = $this->visit('/en_US/plain');

        $group = $shop->locator('.cms-tree > .cms-group');
        Expect::locator($group->getByRole('heading', ['name' => 'In daylight']))->toBeVisible();
        self::assertSame('cms-group', $group->getAttribute('class'));
        self::assertSame('rgba(0, 0, 0, 0)', $group->evaluate('element => getComputedStyle(element).backgroundColor'));
    }
}
