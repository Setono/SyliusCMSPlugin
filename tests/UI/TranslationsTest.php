<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\Expect;
use Playwright\Page\PageInterface as BrowserPage;

/**
 * Translating a published page one locale at a time in the builder, and holding the translation back until it's done.
 * See "In the builder" in docs/spec/localization.md and "Held-back translations" in docs/spec/publishing.md
 */
final class TranslationsTest extends UITestCase
{
    /**
     * @test
     */
    public function it_lets_an_admin_user_add_danish_to_a_published_page_translate_it_and_show_it_once_it_is_done(): void
    {
        self::addLocales('WEB', 'da_DK');
        $page = self::createPage('About us', 'about-us', 'Hello', published: true);
        $this->signInAsAdmin();

        $browser = $this->visit(sprintf('/admin/cms/entry/%s/builder', $page->getUuid()));
        $language = $browser->getByRole('combobox', ['name' => 'Language']);
        Expect::locator($language)->toHaveValue('en_US');

        // Danish has no translation yet, so a banner offers one in place of the preview
        $language->selectOption('da_DK');
        Expect::locator($browser->getByText('This entry has no translation in Danish (Denmark) yet.'))->toBeVisible();
        Expect::locator($browser->getByTitle('Preview'))->toHaveCount(0);

        $browser->getByRole('button', ['name' => 'Add Danish (Denmark) translation'])->click();

        Expect::locator($browser->getByText('This entry has no translation in Danish (Denmark) yet.'))->toHaveCount(0);
        $shown = $browser->getByRole('checkbox', ['name' => 'Show in Danish (Denmark)']);
        Expect::locator($shown)->not()->toBeChecked();
        Expect::locator($browser->getByLabel('Title'))->toHaveValue('About us');
        $this->expectSaved($browser);

        // The preview shows the page in Danish, where the heading falls back to the English text
        $preview = $browser->frameLocator('iframe[title="Preview"]');
        Expect::locator($preview->locator('.cms-tree .cms-heading'))->toHaveText('Hello');

        // The empty Danish heading shows the English text as its placeholder
        // The tree's hint shows the Danish text, which there's none of yet
        $browser->getByRole('button', ['name' => 'Heading', 'exact' => true])->click();
        $text = $browser->getByLabel('Text');
        Expect::locator($text)->toHaveValue('');
        Expect::locator($text)->toHaveAttribute('placeholder', 'Hello');
        Expect::locator($browser->getByRole('button', ['name' => 'Copy it here']))->toBeVisible();
        Expect::locator($browser->getByText('All languages'))->toBeVisible();

        $text->fill('Hej');
        Expect::locator($browser->getByRole('button', ['name' => 'Copy it here']))->toHaveCount(0);
        Expect::locator($preview->locator('.cms-tree .cms-heading'))->toHaveText('Hej');

        // Danish is held back until it's switched on, and switching it on is drafted, so it waits for the publish
        $browser->getByRole('button', ['name' => 'Entry settings'])->click();
        $shown->check();
        $this->expectSaved($browser);
        self::assertSame(404, $this->statusOf('/da_DK/about-us'));

        $browser = $this->visit(sprintf('/admin/cms/entry/%s/builder', $page->getUuid()));
        $browser->getByRole('button', ['name' => 'Publish'])->click();
        Expect::locator($browser->getByText('Published', ['exact' => true]))->toBeVisible();

        $shop = $this->visit('/da_DK/about-us');
        Expect::locator($shop->locator('.cms-tree .cms-heading'))->toHaveText('Hej');
        self::assertSame('About us', $shop->locator('h1')->textContent());

        $shop = $this->visit('/en_US/about-us');
        Expect::locator($shop->locator('.cms-tree .cms-heading'))->toHaveText('Hello');
        self::assertSame(200, $this->statusOf('/en_US/about-us'));
    }

    private function expectSaved(BrowserPage $browser): void
    {
        Expect::locator($browser->getByRole('status'))->toHaveText('Saved');
    }

    /**
     * The status the shop answers a URL with, as the browser gets it
     */
    private function statusOf(string $path): ?int
    {
        return $this->getPage()->goto($this->getBaseUrl() . $path)?->status();
    }
}
