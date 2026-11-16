<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\Expect;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Translation\Fingerprint;

/**
 * Seeing which values are outdated or untranslated in each locale, in the builder and in the admin, and marking an
 * outdated value up to date. See "Completeness and outdated flags" in docs/spec/localization.md
 */
final class OutdatedTranslationsTest extends UITestCase
{
    private const HEADING = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f';

    /**
     * @test
     */
    public function it_flags_a_danish_heading_whose_english_text_changed_and_lets_an_admin_user_mark_it_up_to_date(): void
    {
        $page = self::danishPage();
        $this->signInAsAdmin();

        $browser = $this->visit(sprintf('/admin/cms/entry/%s/builder', $page->getUuid()));
        $language = $browser->getByRole('combobox', ['name' => 'Language']);
        $danish = $language->locator('option[value="da_DK"]');
        Expect::locator($language)->toHaveValue('en_US');
        Expect::locator($danish)->toHaveText('Danish (Denmark)');

        // Changing the English heading makes the Danish one, translated from it, outdated
        $browser->getByRole('button', ['name' => 'Heading Hello', 'exact' => true])->click();
        $browser->getByLabel('Text')->fill('Hello there');
        Expect::locator($danish)->toHaveText('Danish (Denmark) · 1 outdated');
        Expect::locator($browser->getByRole('status'))->toHaveText('Saved');

        // In Danish, the heading shows the new English text it was translated from
        $language->selectOption('da_DK');
        Expect::locator($browser->getByLabel('Text'))->toHaveValue('Hej');
        Expect::locator($browser->getByText('The English (United States) text it was translated from has changed. It now reads:'))->toBeVisible();
        Expect::locator($browser->locator('blockquote'))->toHaveText('Hello there');

        $browser->getByRole('button', ['name' => 'Mark as up to date'])->click();

        Expect::locator($browser->getByRole('button', ['name' => 'Mark as up to date']))->toHaveCount(0);
        Expect::locator($danish)->toHaveText('Danish (Denmark)');
        Expect::locator($browser->getByRole('status'))->toHaveText('Saved');
        self::assertSame(
            ['text' => ['da_DK' => ['from' => 'en_US', 'fingerprint' => Fingerprint::of('Hello there')]]],
            self::reloadPage($page)->getBlockTree()->blocks[0]->translated,
        );
    }

    /**
     * @test
     */
    public function it_shows_a_translation_chip_per_locale_in_the_list_and_on_the_overview_page(): void
    {
        $page = self::danishPage('Hello there');
        self::addLocales('WEB', 'de_DE');
        $this->signInAsAdmin();

        $list = $this->visit('/admin/cms/entries/page');
        $chips = $list->locator(sprintf('tr:has([data-entry="%s"]) [data-column="translations"] [data-translation-chip]', $page->getUuid()));
        Expect::locator($chips)->toHaveCount(3);
        Expect::locator($chips->nth(0))->toContainText('complete');
        Expect::locator($chips->nth(1))->toContainText('1 outdated');
        Expect::locator($chips->nth(2))->toContainText('no translation');
        self::assertSame('line-through', $chips->nth(2)->locator('del')->evaluate('el => getComputedStyle(el).textDecorationLine'));

        $overview = $this->visit(sprintf('/admin/cms/entry/%s', $page->getUuid()));
        $section = $overview->getByRole('region', ['name' => 'Translations']);
        Expect::locator($section->locator('[data-translation-chip="da_DK"]'))->toContainText('1 outdated');
        Expect::locator($section->getByText('Translating happens in the builder, one language at a time.'))->toBeVisible();
    }

    /**
     * A page in English and Danish, whose Danish heading was translated from the English text "Hello"
     */
    private static function danishPage(string $english = 'Hello'): PageInterface
    {
        self::addLocales('WEB', 'da_DK');
        $heading = self::heading(self::HEADING, $english);
        $heading->setValue('text', 'da_DK', 'Hej');
        $heading->setTranslated('text', 'da_DK', ['from' => 'en_US', 'fingerprint' => Fingerprint::of('Hello')]);
        $page = self::createPageWithBlocks('About us', 'about-us', [$heading]);

        self::addTranslation($page, 'da_DK', 'Om os', 'om-os');

        return $page;
    }
}
