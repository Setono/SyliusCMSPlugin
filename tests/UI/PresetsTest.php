<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\Expect;
use Setono\SyliusCMSPlugin\Model\PageInterface;

/**
 * An editor finds the test application's Hero code preset in the block picker's Presets tab, sees it rendered live, and
 * inserts it, which puts its blocks in the tree with the texts its translation keys resolve to. See "Presets" in
 * docs/spec/layouts-and-presets.md and "Block picker" in docs/spec/builder.md
 */
final class PresetsTest extends UITestCase
{
    /**
     * @test
     */
    public function it_lets_an_admin_user_search_the_presets_tab_see_a_preset_rendered_live_and_insert_its_blocks_with_their_translated_text(): void
    {
        $page = self::createPage('About us', 'about-us');
        $this->signInAsAdmin();

        $browser = $this->visit(sprintf('/admin/cms/entry/%s/builder', $page->getUuid()));
        $preview = $browser->frameLocator('iframe[title="Preview"]');
        Expect::locator($preview->locator('.cms-tree'))->toHaveCount(1);

        $browser->getByRole('button', ['name' => 'Add block', 'exact' => true])->click();
        $picker = $browser->getByRole('dialog', ['name' => 'Add block']);
        $picker->getByRole('tab', ['name' => 'Presets'])->click();

        // The presets that fit a page's top level, by category, and the one that needs a product page, with why
        Expect::locator($picker->getByRole('group', ['name' => 'Banners']))->toBeVisible();
        Expect::locator($picker->getByText('Product spotlight'))->toBeVisible();
        Expect::locator($picker->getByText('Needs: Product page'))->toBeVisible();

        $picker->getByRole('searchbox', ['name' => 'Search presets'])->fill('introduction');
        self::assertTexts(['HeroFrom code'], $picker->getByRole('heading', ['level' => 4]));
        Expect::locator($picker->getByText('Product spotlight'))->toHaveCount(0);

        // Rendered live in the preview's channel and locale, with the texts its translation keys resolve to
        $render = $browser->frameLocator('iframe[title="Preview of Hero"]');
        Expect::locator($render->locator('h2.cms-heading'))->toHaveText('Winter sale');
        Expect::locator($render->locator('.cms-rich-text strong'))->toHaveText('50%');

        $picker->getByRole('button', ['name' => 'Insert Hero'])->click();

        Expect::locator($picker)->toHaveCount(0);
        $tree = $browser->getByRole('navigation', ['name' => 'Blocks']);
        Expect::locator($tree->getByRole('button', ['name' => 'Heading Winter sale']))->toBeVisible();
        Expect::locator($tree->getByRole('button', ['name' => 'Rich text']))->toHaveCount(1);
        Expect::locator($browser->getByRole('status'))->toHaveText('Saved');
        Expect::locator($preview->locator('.cms-tree > h2.cms-heading'))->toHaveText('Winter sale');
        Expect::locator($preview->locator('.cms-tree > .cms-rich-text strong'))->toHaveText('50%');

        // Saved to the page with new keys, not the preset's
        $saved = self::reloadPage($page);
        self::assertInstanceOf(PageInterface::class, $saved);
        $blocks = $saved->getBlockTree()->blocks;
        self::assertSame(['heading', 'rich_text'], array_map(static fn ($block): string => $block->type, $blocks));
        self::assertNotSame('0193a5be-0000-7000-8000-00000000a001', $blocks[0]->key);
        self::assertSame(['en_US' => 'Winter sale'], $blocks[0]->fields['text'] ?? null);
    }
}
