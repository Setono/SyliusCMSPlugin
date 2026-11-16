<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\Expect;
use Playwright\Locator\LocatorInterface;
use Playwright\Page\PageInterface as BrowserPage;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;

/**
 * Hiding a block from shoppers in one language with a visibility condition in the builder.
 * See "Visibility conditions" in docs/spec/targeting.md and "Right pane: the selection" in docs/spec/builder.md
 */
final class VisibilityConditionsTest extends UITestCase
{
    /**
     * @test
     */
    public function it_lets_an_admin_user_hide_a_block_in_danish_struck_through_in_the_tree_while_previewing_danish_and_missing_from_the_danish_page(): void
    {
        self::addLocales('WEB', 'da_DK');
        $page = self::createPageWithBlocks('About us', 'about-us', [
            self::bilingualHeading('0193a5be-0000-7000-8000-000000000001', 'Welcome', 'Velkommen'),
            self::bilingualHeading('0193a5be-0000-7000-8000-000000000002', 'Not for Danes', 'Ikke for danskere'),
        ]);
        self::addTranslation($page, 'da_DK', 'Om os', 'om-os');
        $this->signInAsAdmin();

        $browser = $this->visit(sprintf('/admin/cms/entry/%s/builder', $page->getUuid()));
        $tree = $browser->getByRole('navigation', ['name' => 'Blocks']);
        $tree->getByRole('button', ['name' => 'Heading Not for Danes'])->click();

        // Locale is not Danish
        $selection = $browser->getByRole('complementary');
        Expect::locator($selection->getByRole('heading', ['name' => 'Visibility']))->toBeVisible();
        $selection->getByRole('button', ['name' => 'Add condition'])->click();
        $condition = $selection->getByRole('group', ['name' => 'Condition 1']);
        $condition->getByRole('combobox', ['name' => 'Kind'])->selectOption('locale');
        $condition->getByRole('combobox', ['name' => 'Matches when it'])->selectOption('not');
        $condition->getByLabel('Languages')->selectOption('da_DK');
        $this->expectSaved($browser);

        // Not struck through while previewing English
        self::assertNull(self::row($tree, 'Heading Not for Danes')->getAttribute('data-hidden'));
        $preview = $browser->frameLocator('iframe[title="Preview"]');
        Expect::locator($preview->locator('.cms-tree .cms-heading'))->toHaveCount(2);

        // Struck through, with the eye-slash icon, and dropped from the preview while previewing Danish
        $browser->getByRole('combobox', ['name' => 'Language'])->selectOption('da_DK');
        $hidden = self::row($tree, 'Heading Ikke for danskere');
        Expect::locator($hidden->getByRole('img', ['name' => 'Hidden in this channel and language']))->toBeVisible();
        self::assertSame('true', $hidden->getAttribute('data-hidden'));
        self::assertNull(self::row($tree, 'Heading Velkommen')->getAttribute('data-hidden'));
        self::assertSame('line-through', $hidden->getByText('Heading', ['exact' => true])->evaluate('el => getComputedStyle(el).textDecorationLine'));
        Expect::locator($preview->locator('.cms-tree .cms-heading'))->toHaveCount(1);
        Expect::locator($preview->locator('.cms-tree .cms-heading'))->toHaveText('Velkommen');

        $browser->getByRole('button', ['name' => 'Publish'])->click();
        Expect::locator($browser->getByText('Published', ['exact' => true]))->toBeVisible();

        $shop = $this->visit('/da_DK/om-os');
        Expect::locator($shop->locator('.cms-tree .cms-heading'))->toHaveCount(1);
        Expect::locator($shop->locator('.cms-tree .cms-heading'))->toHaveText('Velkommen');

        $shop = $this->visit('/en_US/about-us');
        Expect::locator($shop->locator('.cms-tree .cms-heading'))->toHaveCount(2);
        Expect::locator($shop->locator('.cms-tree .cms-heading')->nth(1))->toHaveText('Not for Danes');
    }

    private static function bilingualHeading(string $key, string $english, string $danish): StoredBlock
    {
        $heading = new StoredBlock($key, 'heading', 1);
        $heading->setValue('text', 'en_US', $english);
        $heading->setValue('text', 'da_DK', $danish);

        return $heading;
    }

    /**
     * The tree's row of the block whose select button has this name
     */
    private static function row(LocatorInterface $tree, string $name): LocatorInterface
    {
        return $tree->getByRole('button', ['name' => $name])->locator('xpath=..');
    }

    private function expectSaved(BrowserPage $browser): void
    {
        Expect::locator($browser->getByRole('status'))->toHaveText('Saved');
    }
}
