<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Doctrine\Persistence\ObjectManager;
use Playwright\Assertions\Expect;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Tree\StoredLayout;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;

/**
 * Picking a layout in the create dialog and switching it in the builder's entry settings, with the preview reloading.
 * See "Picking and switching" in docs/spec/layouts-and-presets.md
 */
final class LayoutsTest extends UITestCase
{
    /**
     * @test
     */
    public function an_admin_user_creates_a_page_in_the_test_apps_layout_switches_it_to_standard_and_hides_its_title(): void
    {
        $this->signInAsAdmin();

        // The create dialog shows the layouts as wireframe cards, the default picked
        $browser = $this->visit('/admin/cms/entries/page');
        $browser->getByRole('link', ['name' => 'Create'])->click();
        $dialog = $browser->getByRole('dialog', ['name' => 'New Page']);
        $layouts = $dialog->getByRole('group', ['name' => 'Layout']);
        Expect::locator($layouts->getByRole('radio', ['name' => 'Standard']))->toBeChecked();
        Expect::locator($layouts->locator('img'))->toHaveAttribute('src', '/bundles/setonosyliuscmsplugin/thumbnail/layout/page/standard.svg');
        self::assertSame('2px', $layouts->locator('.cms-layout-card')->first()->evaluate('card => getComputedStyle(card).borderTopWidth'));

        $layouts->getByText('Landing', ['exact' => true])->click();
        Expect::locator($layouts->getByRole('radio', ['name' => 'Landing']))->toBeChecked();
        $dialog->getByLabel('Title')->fill('Summer sale');
        $dialog->getByRole('button', ['name' => 'Create'])->click();

        // The builder opens with the page in the test application's layout, which doesn't print the title
        Expect::locator($browser->getByRole('heading', ['level' => 1]))->toHaveText('Summer sale');
        $settings = $browser->getByRole('tabpanel', ['name' => 'Settings']);
        Expect::locator($settings->getByRole('radio', ['name' => 'Landing']))->toBeChecked();
        Expect::locator($browser->getByLabel('Title', ['exact' => true]))->toHaveAccessibleDescription('Not shown in this layout');
        $preview = $browser->frameLocator('iframe[title="Preview"]');
        Expect::locator($preview->locator('.app-landing'))->toBeAttached();
        Expect::locator($preview->getByRole('heading', ['level' => 1]))->toHaveCount(0);

        // A layout field reloads the preview
        $browser->getByLabel('Tagline')->fill('Big news');
        Expect::locator($preview->locator('.app-landing__tagline'))->toHaveText('Big news');

        // Switching to Standard reloads the preview in that layout, with the title
        $settings->getByRole('group', ['name' => 'Layout'])->getByText('Standard', ['exact' => true])->click();
        Expect::locator($settings->getByRole('radio', ['name' => 'Standard']))->toBeChecked();
        Expect::locator($preview->getByRole('heading', ['level' => 1]))->toHaveText('Summer sale');
        Expect::locator($preview->locator('.app-landing'))->toHaveCount(0);
        Expect::locator($settings->getByRole('checkbox', ['name' => 'Show title']))->toBeChecked();

        // Unticking Show title takes the title away
        $settings->getByRole('checkbox', ['name' => 'Show title'])->uncheck();
        Expect::locator($browser->getByRole('status'))->toHaveText('Saved');
        Expect::locator($preview->getByRole('heading', ['level' => 1]))->toHaveCount(0);
        Expect::locator($preview->locator('.cms-tree'))->toBeAttached();

        self::assertEquals(new StoredLayout('standard', 1, ['showTitle' => false]), self::page('summer-sale')->getBlockTree()->layout);
    }

    /**
     * @test
     */
    public function the_builder_flags_a_page_whose_layout_was_removed_until_the_admin_user_picks_one(): void
    {
        $this->signInAsAdmin();
        $page = self::createPage('About us', 'about-us', 'Hello');
        $page->setBlockTree(new StoredTree(new StoredLayout('gone', 1), $page->getBlockTree()->blocks));
        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('setono_sylius_cms.manager.page');
        $manager->flush();

        $browser = $this->visit(sprintf('/admin/cms/entry/%s/builder', $page->getUuid()));
        $settings = $browser->getByRole('tabpanel', ['name' => 'Settings']);
        Expect::locator($settings->getByRole('status'))->toHaveText('Its layout "gone" was removed from the code or disabled, so it shows in the default layout, Standard, until you pick one.');
        // Meanwhile the page shows in the default layout
        Expect::locator($browser->frameLocator('iframe[title="Preview"]')->getByRole('heading', ['level' => 1]))->toHaveText('About us');

        $settings->getByRole('group', ['name' => 'Layout'])->getByText('Standard', ['exact' => true])->click();

        Expect::locator($settings->getByText('was removed from the code'))->toHaveCount(0);
        Expect::locator($browser->getByRole('status'))->toHaveText('Saved');
        self::assertSame('standard', self::page('about-us')->getBlockTree()->layout?->code);
    }

    private static function page(string $slug): PageInterface
    {
        /** @var RepositoryInterface<PageInterface> $pages */
        $pages = self::getContainer()->get('setono_sylius_cms.repository.page');
        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('setono_sylius_cms.manager.page');
        $manager->clear();
        foreach ($pages->findAll() as $page) {
            if ($page->getTranslation('en_US')->getSlug() === $slug) {
                return $page;
            }
        }

        self::fail(sprintf('There is no page with the slug "%s"', $slug));
    }
}
