<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\Expect;
use Playwright\Frame\FrameLocatorInterface;
use Playwright\Locator\LocatorInterface;
use Playwright\Page\PageInterface;
use Setono\SyliusCMSPlugin\Model\PageInterface as CmsPageInterface;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;

/**
 * Undo and redo, selecting several blocks, and moving, duplicating and deleting them. See "Top bar", "Left pane: the
 * block tree", "Right pane: the selection" and "Working in the preview" in docs/spec/builder.md
 */
final class EditingToolsTest extends UITestCase
{
    private const GROUP = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e40';

    private const INSIDE = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e41';

    private const FIRST = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e42';

    private const SECOND = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e43';

    /**
     * @test
     */
    public function it_lets_an_admin_user_duplicate_a_group_undo_it_and_delete_two_headings_selected_together_then_redo_the_delete(): void
    {
        $entry = self::createPageWithBlocks('About us', 'about-us', [
            new StoredBlock(self::GROUP, 'group', 1, children: [self::heading(self::INSIDE, 'Inside')]),
            self::heading(self::FIRST, 'First'),
            self::heading(self::SECOND, 'Second'),
        ]);
        [$page, $preview] = $this->openBuilder($entry);
        $tree = $page->getByRole('navigation', ['name' => 'Blocks']);
        $groups = $tree->getByRole('button', ['name' => 'Group', 'exact' => true]);

        // Duplicate the Group, with the heading inside it, from its toolbar in the preview
        $groups->click();
        $preview->getByRole('toolbar')->getByRole('button', ['name' => 'Duplicate'])->click();

        Expect::locator($groups)->toHaveCount(2);
        Expect::locator($tree->getByRole('button', ['name' => 'Heading Inside']))->toHaveCount(2);
        Expect::locator($preview->locator('.cms-tree > .cms-group'))->toHaveCount(2);
        Expect::locator($preview->locator('.cms-group .cms-heading'))->toHaveCount(2);

        // Undo it from the top bar
        $page->getByRole('banner')->getByRole('button', ['name' => 'Undo'])->click();

        Expect::locator($groups)->toHaveCount(1);
        Expect::locator($preview->locator('.cms-tree > .cms-group'))->toHaveCount(1);

        // ⌘-click (Ctrl-click elsewhere) selects both headings, and the right pane deletes both
        $tree->getByRole('button', ['name' => 'Heading First'])->click();
        $tree->getByRole('button', ['name' => 'Heading Second'])->click(['modifiers' => ['ControlOrMeta']]);
        $selection = $page->getByRole('complementary');
        Expect::locator($selection->getByRole('heading', ['name' => '2 blocks selected']))->toBeVisible();
        $selection->getByRole('button', ['name' => 'Delete block'])->click();

        Expect::locator($tree->getByRole('button', ['name' => 'Heading First']))->toHaveCount(0);
        Expect::locator($tree->getByRole('button', ['name' => 'Heading Second']))->toHaveCount(0);
        Expect::locator($preview->locator('.cms-tree > .cms-heading'))->toHaveCount(0);

        // Undo the delete with the keyboard, then redo it
        $page->keyboard()->press('ControlOrMeta+z');
        self::assertTexts(['First', 'Second'], $preview->locator('.cms-tree > .cms-heading'));

        $page->keyboard()->press('ControlOrMeta+Shift+z');
        Expect::locator($tree->getByRole('button', ['name' => 'Heading First']))->toHaveCount(0);
        Expect::locator($preview->locator('.cms-tree > .cms-heading'))->toHaveCount(0);

        // What's saved is the working copy as it is now
        Expect::locator($page->getByRole('status'))->toHaveText('Saved');
        self::assertSame([self::GROUP, self::INSIDE], self::reloadPage($entry)->getBlockTree()->keys());
    }

    /**
     * @test
     */
    public function it_lets_an_admin_user_move_a_heading_up_from_its_toolbar_in_the_preview(): void
    {
        $entry = self::createPageWithBlocks('About us', 'about-us', [self::heading(self::FIRST, 'First'), self::heading(self::SECOND, 'Second')]);
        [$page, $preview] = $this->openBuilder($entry);

        $preview->getByRole('heading', ['name' => 'Second'])->click();
        $preview->getByRole('toolbar')->getByRole('button', ['name' => 'Move up'])->click();

        self::assertTexts(['Second', 'First'], $preview->locator('.cms-tree > .cms-heading'));
        self::assertTexts(['Second', 'First'], $page->getByRole('navigation', ['name' => 'Blocks'])->locator('li [aria-pressed]'), contains: true);
        Expect::locator($page->getByRole('status'))->toHaveText('Saved');
        self::assertSame([self::SECOND, self::FIRST], self::reloadPage($entry)->getBlockTree()->keys());
    }

    /**
     * @return array{PageInterface, FrameLocatorInterface}
     */
    private function openBuilder(CmsPageInterface $entry): array
    {
        $this->signInAsAdmin();

        $page = $this->visit(sprintf('/admin/cms/entry/%s/builder', $entry->getUuid()));
        $preview = $page->frameLocator('iframe[title="Preview"]');

        // The bridge has drawn its controls into the page once the builder has set it up
        Expect::locator($preview->locator('[data-cms-bridge]'))->toBeAttached();

        return [$page, $preview];
    }

    /**
     * Expect::toHaveText() with a list needs allTextContents(), which playwright-php's server doesn't know, so each
     * element is checked on its own
     *
     * @param list<string> $texts
     */
    private static function assertTexts(array $texts, LocatorInterface $locator, bool $contains = false): void
    {
        Expect::locator($locator)->toHaveCount(count($texts));
        foreach ($texts as $index => $text) {
            if ($contains) {
                Expect::locator($locator->nth($index))->toContainText($text);
            } else {
                Expect::locator($locator->nth($index))->toHaveText($text);
            }
        }
    }
}
