<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\Expect;
use Playwright\Frame\FrameLocatorInterface;
use Playwright\Page\PageInterface;
use Setono\SyliusCMSPlugin\Model\PageInterface as CmsPageInterface;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;

/**
 * Typing text and rich text in place in the builder's preview, with the field in the right pane following.
 * See "Working in the preview" in docs/spec/builder.md
 */
final class TypingInPlaceTest extends UITestCase
{
    private const RICH_TEXT = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e51';

    /**
     * @test
     */
    public function an_admin_user_types_over_a_heading_in_the_preview_and_sees_the_right_pane_follow(): void
    {
        [$page, $preview] = $this->openBuilder(self::createPage('About us', 'about-us', 'Hello'));
        $heading = $preview->getByRole('heading', ['name' => 'Hello']);

        $heading->click();
        $page->keyboard()->press('ControlOrMeta+a');
        $page->keyboard()->type('Welcome to our shop');

        Expect::locator($page->getByLabel('Text'))->toHaveValue('Welcome to our shop');
        Expect::locator($page->getByRole('status'))->toHaveText('Saved');

        // Enter ends the typing, and the preview shows the heading as the shop renders it
        $page->keyboard()->press('Enter');
        $heading = $preview->locator('.cms-tree .cms-heading');
        Expect::locator($heading)->toHaveText('Welcome to our shop');
        Expect::locator($heading)->not()->toHaveAttribute('contenteditable', 'plaintext-only');

        // And an edit in the right pane shows in the preview again
        $page->getByLabel('Text')->fill('Welcome back');
        Expect::locator($heading)->toHaveText('Welcome back');
    }

    /**
     * @test
     */
    public function an_admin_user_bolds_a_word_of_rich_text_in_place(): void
    {
        [$page, $preview] = $this->openBuilder(self::createPageWithBlocks('Story', 'story', [self::richText(self::RICH_TEXT, 'Hello bold world')]));

        $preview->locator('.cms-rich-text p')->click();
        $page->keyboard()->press('End');
        foreach (range(1, 6) as $ignored) {
            $page->keyboard()->press('ArrowLeft');
        }
        foreach (range(1, 4) as $ignored) {
            $page->keyboard()->press('Shift+ArrowLeft');
        }
        $bold = $preview->getByRole('toolbar', ['name' => 'Formatting'])->getByRole('button', ['name' => 'Bold']);
        $bold->click();

        Expect::locator($bold)->toHaveAttribute('aria-pressed', 'true');
        Expect::locator($preview->locator('.cms-rich-text strong'))->toHaveText('bold');
        // The right pane's editor follows, with the same mark
        Expect::locator($page->getByRole('textbox', ['name' => 'Content'])->locator('strong'))->toHaveText('bold');
        Expect::locator($page->getByRole('status'))->toHaveText('Saved');

        // Typing in place uses the right pane's schema and its shortcuts: italic with the keyboard
        $page->keyboard()->press('ControlOrMeta+i');
        Expect::locator($page->getByRole('textbox', ['name' => 'Content'])->locator('strong em, em strong'))->toHaveText('bold');

        $page->keyboard()->press('Escape');
        Expect::locator($preview->getByRole('toolbar', ['name' => 'Formatting']))->toBeHidden();
        Expect::locator($preview->locator('.cms-rich-text p'))->toHaveText('Hello bold world');
        Expect::locator($preview->locator('.cms-rich-text strong'))->toHaveText('bold');
    }

    /**
     * @test
     */
    public function an_admin_user_types_an_accordion_items_title_in_place_without_opening_it(): void
    {
        $item = new StoredBlock('0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e53', 'accordion_item', 1);
        $item->setValue('title', 'en_US', 'Shipping');
        $accordion = new StoredBlock('0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e52', 'accordion', 1, children: [$item]);
        [$page, $preview] = $this->openBuilder(self::createPageWithBlocks('FAQ', 'faq', [$accordion]));
        $title = $preview->locator('.cms-accordion-item__title');

        $title->click();
        $page->keyboard()->press('ControlOrMeta+a');
        $page->keyboard()->type('Free shipping');

        Expect::locator($page->getByLabel('Title'))->toHaveValue('Free shipping');
        Expect::locator($title)->toHaveText('Free shipping');
        Expect::locator($preview->locator('details.cms-accordion-item'))->not()->toHaveAttribute('open', '');
        Expect::locator($page->getByRole('status'))->toHaveText('Saved');
    }

    /**
     * A rich text block with one paragraph of text in en_US
     */
    private static function richText(string $key, string $text): StoredBlock
    {
        $block = new StoredBlock($key, 'rich_text', 1);
        $block->setValue('content', 'en_US', ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]]]]);

        return $block;
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
}
