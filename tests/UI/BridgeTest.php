<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\Expect;
use Playwright\Frame\FrameLocatorInterface;
use Playwright\Locator\LocatorInterface;
use Playwright\Page\PageInterface;
use Setono\SyliusCMSPlugin\Model\PageInterface as CmsPageInterface;

/**
 * The bridge between the builder and its preview: selection linked both ways, changes patched into the page without
 * a reload, the cms:render event, and + between blocks. See "The bridge" in docs/spec/builder.md
 */
final class BridgeTest extends UITestCase
{
    private const FIRST = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f';

    private const SECOND = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e50';

    /**
     * @test
     */
    public function it_lets_an_admin_user_click_a_heading_in_the_preview_and_see_it_selected_in_the_tree(): void
    {
        [$page, $preview] = $this->openBuilder(self::twoHeadings());

        $preview->getByRole('heading', ['name' => 'Second'])->click();

        Expect::locator(self::treeRow($page, 'Heading Second'))->toHaveAttribute('aria-pressed', 'true');
        Expect::locator(self::treeRow($page, 'Heading First'))->toHaveAttribute('aria-pressed', 'false');
        Expect::locator($page->getByLabel('Text'))->toHaveValue('Second');
        Expect::locator($preview->locator('[data-cms-bridge] [data-outline="selected"]'))->toBeVisible();
    }

    /**
     * @test
     */
    public function it_lets_an_admin_user_select_a_block_in_the_tree_and_see_it_outlined_in_the_preview(): void
    {
        [$page, $preview] = $this->openBuilder(self::twoHeadings());
        $outline = $preview->locator('[data-cms-bridge] [data-outline="selected"]');
        Expect::locator($outline)->toBeHidden();

        self::treeRow($page, 'Heading Second')->click();

        Expect::locator($outline)->toBeVisible();
        $outlined = $outline->boundingBox();
        $heading = $preview->getByRole('heading', ['name' => 'Second'])->boundingBox();
        self::assertNotNull($outlined);
        self::assertNotNull($heading);
        self::assertEqualsWithDelta($heading['y'], $outlined['y'], 1.0, 'The outline is drawn around the selected heading');
    }

    /**
     * @test
     */
    public function it_lets_an_admin_user_edit_a_headings_text_and_see_the_preview_change_without_a_reload(): void
    {
        [$page, $preview] = $this->openBuilder(self::createPage('About us', 'about-us', 'Hello'));
        $body = $preview->locator('body');
        $body->evaluate('() => { window.cmsTestPage = "the page before the change"; }');
        // A test listener on the body, so it only hears the event if it bubbles
        $body->evaluate('body => { window.cmsRenders = []; body.addEventListener("cms:render", (event) => window.cmsRenders.push({ ...event.detail, tag: event.target.tagName })); }');

        $preview->getByRole('heading', ['name' => 'Hello'])->click();
        $page->getByLabel('Text')->fill('Hello world');

        Expect::locator($preview->locator('.cms-tree .cms-heading'))->toHaveText('Hello world');
        Expect::locator($page->getByRole('status'))->toHaveText('Saved');
        self::assertSame('the page before the change', $body->evaluate('() => window.cmsTestPage'), 'The preview was patched, not reloaded');

        $renders = $body->evaluate('() => window.cmsRenders');
        self::assertIsArray($renders);
        self::assertContains(['key' => self::FIRST, 'type' => 'heading', 'tag' => 'H2'], $renders, "cms:render bubbles up from the patched block's root element");
    }

    /**
     * @test
     */
    public function it_lets_an_admin_user_add_a_heading_between_two_blocks_with_the_plus_of_the_block_under_the_pointer(): void
    {
        [$page, $preview] = $this->openBuilder(self::twoHeadings());

        // Nothing is selected: the + is the one of the block under the pointer
        $preview->getByRole('heading', ['name' => 'First'])->hover();
        $preview->getByRole('button', ['name' => 'Add block below'])->click();
        $page->getByRole('dialog', ['name' => 'Add block'])->getByRole('button', ['name' => 'Heading', 'exact' => true])->click();
        $page->getByLabel('Text')->fill('Middle');

        self::assertTexts(['First', 'Middle', 'Second'], $preview->locator('.cms-tree .cms-heading'));
        self::assertTexts(['First', 'Middle', 'Second'], $page->getByRole('navigation')->locator('li [aria-pressed]'), contains: true);
        Expect::locator($page->getByRole('status'))->toHaveText('Saved');
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

    private static function twoHeadings(): CmsPageInterface
    {
        return self::createPageWithBlocks('About us', 'about-us', [self::heading(self::FIRST, 'First'), self::heading(self::SECOND, 'Second')]);
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

    private static function treeRow(PageInterface $page, string $name): LocatorInterface
    {
        return $page->getByRole('navigation')->getByRole('button', ['name' => $name, 'exact' => true]);
    }
}
