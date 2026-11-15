<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\Expect;
use Playwright\Locator\LocatorInterface;
use Playwright\Page\PageInterface;

final class RichTextAndAccordionTest extends UITestCase
{
    /**
     * @test
     */
    public function it_lets_an_admin_user_write_formatted_text_and_an_accordion_that_shoppers_open(): void
    {
        $this->signInAsAdmin();
        $entry = self::createPage('Story', 'story');

        $page = $this->visit(sprintf('/admin/cms/entry/%s/builder', $entry->getUuid()));
        $tree = $page->getByRole('navigation', ['name' => 'Blocks']);
        $addBlock = $page->getByRole('button', ['name' => 'Add block', 'exact' => true]);

        // A paragraph, then "bold" selected with the keyboard and made bold from the toolbar
        $this->pick($page, $addBlock, 'Rich text');
        $content = $page->getByRole('textbox', ['name' => 'Content']);
        $content->click();
        $content->pressSequentially('Hello bold world');
        foreach (range(1, 6) as $ignored) {
            $page->keyboard()->press('ArrowLeft');
        }
        foreach (range(1, 4) as $ignored) {
            $page->keyboard()->press('Shift+ArrowLeft');
        }
        $bold = $page->getByRole('button', ['name' => 'Bold', 'exact' => true]);
        $bold->click();
        Expect::locator($bold)->toHaveAttribute('aria-pressed', 'true');
        Expect::locator($content->locator('strong'))->toHaveText('bold');

        // An Accordion comes with its one item, whose title and content are filled in
        $this->pick($page, $addBlock, 'Accordion');
        Expect::locator($tree->getByRole('button', ['name' => 'Accordion item', 'exact' => true]))->toHaveCount(1);
        $tree->getByRole('button', ['name' => 'Accordion item', 'exact' => true])->click();
        $page->getByLabel('Title')->fill('Shipping');
        $page->getByRole('textbox', ['name' => 'Content'])->click();
        $page->getByRole('textbox', ['name' => 'Content'])->pressSequentially('We ship within a day.');

        Expect::locator($page->getByRole('status'))->toHaveText('Saved');
        $page->getByRole('button', ['name' => 'Publish'])->click();
        Expect::locator($page->getByText('Published', ['exact' => true]))->toBeVisible();

        $page = $this->visit('/en_US/story');
        Expect::locator($page->locator('.cms-tree > .cms-rich-text p'))->toHaveText('Hello bold world');
        Expect::locator($page->locator('.cms-tree > .cms-rich-text strong'))->toHaveText('bold');

        $item = $page->locator('.cms-tree > .cms-accordion > details.cms-accordion-item');
        $answer = $item->locator('.cms-accordion-item__content');
        Expect::locator($answer)->toBeHidden();

        $item->getByText('Shipping', ['exact' => true])->click();

        Expect::locator($item)->toHaveAttribute('open', '');
        Expect::locator($answer)->toBeVisible();
        Expect::locator($answer)->toHaveText('We ship within a day.');
    }

    /**
     * Opens the block picker from a + button and picks a block type
     */
    private function pick(PageInterface $page, LocatorInterface $button, string $blockType): void
    {
        $button->click();
        $page->getByRole('dialog', ['name' => 'Add block'])->getByRole('button', ['name' => $blockType, 'exact' => true])->click();
    }
}
