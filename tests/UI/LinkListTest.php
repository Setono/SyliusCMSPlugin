<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\Expect;

/**
 * The Link list block with its Link children, and links in rich text: an editor builds a list of two links, edits one's
 * label in its row in the tree, links a word of a Rich text block to a page from the editor's toolbar, publishes, and
 * shoppers follow both links. See "Link list and Link" in docs/spec/blocks.md and "Rich text" in
 * docs/spec/content-model.md
 */
final class LinkListTest extends UITestCase
{
    /**
     * @test
     */
    public function it_lets_an_admin_user_build_a_link_list_and_link_a_word_in_rich_text_that_shoppers_follow(): void
    {
        $this->signInAsAdmin();
        self::createPage('Our story', 'our-story', published: true);
        self::createPage('Our team', 'team', published: true);
        $entry = self::createPage('Help', 'help');

        $page = $this->visit(sprintf('/admin/cms/entry/%s/builder', $entry->getUuid()));
        $tree = $page->getByRole('navigation', ['name' => 'Blocks']);
        $addBlock = $page->getByRole('button', ['name' => 'Add block', 'exact' => true]);

        // A Link list comes with its first Link, and + inside it adds the second straight away
        $addBlock->click();
        $page->getByRole('dialog', ['name' => 'Add block'])->getByRole('button', ['name' => 'Link list', 'exact' => true])->click();
        Expect::locator($tree->getByRole('button', ['name' => 'Link', 'exact' => true]))->toHaveCount(1);
        $page->getByLabel('Heading')->fill('Customer service');
        $tree->getByRole('button', ['name' => 'Add a block inside Link list', 'exact' => true])->click();
        Expect::locator($tree->getByRole('button', ['name' => 'Link', 'exact' => true]))->toHaveCount(2);

        // The first links to a page found with the search, the second to a URL with its label in the right pane
        $tree->getByRole('button', ['name' => 'Link', 'exact' => true])->first()->click();
        $page->getByRole('button', ['name' => 'Choose…', 'exact' => true])->click();
        $page->getByRole('combobox', ['name' => 'Search: Entry'])->fill('story');
        $page->getByRole('option', ['name' => 'Our story'])->click();
        Expect::locator($tree->getByRole('textbox', ['name' => 'Link label'])->first())->toHaveAttribute('placeholder', 'Our story');

        $tree->getByRole('button', ['name' => 'Link', 'exact' => true])->last()->click();
        $page->getByRole('combobox', ['name' => 'Link'])->selectOption('url');
        $page->getByRole('textbox', ['name' => 'URL'])->fill('/en_US/team');
        $page->getByRole('textbox', ['name' => 'Label', 'exact' => true])->fill('Meet us');

        // The first link's label is typed in its row in the tree
        $tree->getByRole('textbox', ['name' => 'Link label'])->first()->fill('Read our story');
        $previewLinks = $page->frameLocator('iframe')->locator('.cms-tree > .cms-link-list a');
        Expect::locator($previewLinks)->toHaveCount(2);
        Expect::locator($previewLinks->first())->toHaveText('Read our story');
        Expect::locator($previewLinks->last())->toHaveText('Meet us');

        // In a Rich text block, "the team" is selected with the keyboard and linked to a page from the toolbar
        $addBlock->click();
        $page->getByRole('dialog', ['name' => 'Add block'])->getByRole('button', ['name' => 'Rich text', 'exact' => true])->click();
        $content = $page->getByRole('textbox', ['name' => 'Content']);
        $content->click();
        $content->pressSequentially('Meet the team today');
        foreach (range(1, 6) as $ignored) {
            $page->keyboard()->press('ArrowLeft');
        }
        foreach (range(1, 8) as $ignored) {
            $page->keyboard()->press('Shift+ArrowLeft');
        }
        $page->getByRole('toolbar', ['name' => 'Formatting'])->getByRole('button', ['name' => 'Link', 'exact' => true])->click();
        $linking = $page->getByRole('group', ['name' => 'Link']);
        Expect::locator($linking->getByRole('combobox', ['name' => 'Links to']))->toHaveValue('entry');
        $linking->getByRole('button', ['name' => 'Choose…', 'exact' => true])->click();
        $page->getByRole('combobox', ['name' => 'Search: Entry'])->fill('team');
        $page->getByRole('option', ['name' => 'Our team'])->click();
        $linking->getByRole('button', ['name' => 'Apply', 'exact' => true])->click();
        Expect::locator($content->locator('a'))->toHaveText('the team');
        Expect::locator($page->frameLocator('iframe')->locator('.cms-tree > .cms-rich-text a'))->toHaveText('the team');

        Expect::locator($page->getByRole('status'))->toHaveText('Saved');
        $page->getByRole('button', ['name' => 'Publish'])->click();
        Expect::locator($page->getByText('Published', ['exact' => true]))->toBeVisible();

        $shop = $this->visit('/en_US/help');
        Expect::locator($shop->locator('.cms-tree > .cms-link-list .cms-link-list__heading'))->toHaveText('Customer service');
        $links = $shop->locator('.cms-tree > .cms-link-list li.cms-link');
        Expect::locator($links)->toHaveCount(2);
        Expect::locator($links->first())->toHaveText('Read our story');
        Expect::locator($links->last())->toHaveText('Meet us');
        $shop->getByRole('link', ['name' => 'Read our story'])->click();
        Expect::locator($shop->locator('h1'))->toContainText('Our story');
        self::assertStringEndsWith('/en_US/our-story', $shop->url());

        $shop = $this->visit('/en_US/help');
        Expect::locator($shop->locator('.cms-tree > .cms-rich-text p'))->toHaveText('Meet the team today');
        $shop->locator('.cms-tree > .cms-rich-text')->getByRole('link', ['name' => 'the team'])->click();
        Expect::locator($shop->locator('h1'))->toContainText('Our team');
        self::assertStringEndsWith('/en_US/team', $shop->url());
    }
}
