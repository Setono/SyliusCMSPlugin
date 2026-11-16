<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Doctrine\Persistence\ObjectManager;
use Playwright\Assertions\Expect;
use Playwright\Locator\LocatorInterface;
use Playwright\Page\PageInterface as BrowserPage;
use Setono\SyliusCMSPlugin\Model\PageTranslation;

/**
 * The overview page's Where it shows grid, which says per channel and locale whether shoppers see the entry, and if
 * they don't, why. See "Entry overview page" in docs/spec/admin.md
 */
final class WhereItShowsTest extends UITestCase
{
    /**
     * @test
     */
    public function an_admin_user_sees_danish_held_back_switches_it_on_and_publishes_and_then_sees_both_live(): void
    {
        self::addLocales('WEB', 'da_DK');
        $page = self::createPage('About us', 'about-us', 'Hello', published: true);
        $danish = new PageTranslation();
        $danish->setLocale('da_DK');
        $danish->setTitle('Om os');
        $danish->setSlug('om-os');
        $danish->setShown(false);
        $page->addTranslation($danish);
        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('setono_sylius_cms.manager.page');
        $manager->flush();
        $this->signInAsAdmin();

        $browser = $this->visit(sprintf('/admin/cms/entry/%s', $page->getUuid()));
        Expect::locator($this->cell($browser, 'en_US'))->toContainText('Live');
        Expect::locator($this->cell($browser, 'da_DK'))->toContainText('Held back');
        Expect::locator($browser->getByRole('region', ['name' => 'Where it shows'])->locator('[data-audience]'))->toContainText('Everyone');
        self::assertSame('held_back', $this->cell($browser, 'da_DK')->getAttribute('data-reason'));

        // Danish is switched on in the builder, which drafts it, so it shows once it's published
        $browser->getByRole('link', ['name' => 'Open in builder'])->click();
        $browser->getByRole('combobox', ['name' => 'Language'])->selectOption('da_DK');
        $browser->getByRole('button', ['name' => 'Entry settings'])->click();
        $browser->getByRole('checkbox', ['name' => 'Show in Danish (Denmark)'])->check();
        Expect::locator($browser->getByRole('status'))->toHaveText('Saved');
        $browser->getByRole('button', ['name' => 'Publish'])->click();
        Expect::locator($browser->getByText('Published', ['exact' => true]))->toBeVisible();

        $browser->getByRole('link', ['name' => 'Back'])->click();
        Expect::locator($this->cell($browser, 'en_US'))->toContainText('Live');
        Expect::locator($this->cell($browser, 'da_DK'))->toContainText('Live');
        self::assertSame('live', $this->cell($browser, 'da_DK')->getAttribute('data-reason'));
    }

    /**
     * The grid's cell of the channel WEB in a locale
     */
    private function cell(BrowserPage $browser, string $localeCode): LocatorInterface
    {
        return $browser->getByRole('region', ['name' => 'Where it shows'])->locator(sprintf('[data-channel="WEB"] td[data-locale="%s"]', $localeCode));
    }
}
