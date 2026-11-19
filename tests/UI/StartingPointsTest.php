<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\Expect;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredLayout;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;

/**
 * An editor saves a page's whole tree, on the landing layout, as a starting point from the builder's entry settings,
 * then creates a new page from it in the create dialog, and finds the same blocks and layout in the builder. See
 * "Starting points" and "Editor presets" in docs/spec/layouts-and-presets.md and "Create dialog" in docs/spec/admin.md
 */
final class StartingPointsTest extends UITestCase
{
    private const SUMMER = '0193a5be-0000-7000-8000-000000000001';

    private const SHOP_NOW = '0193a5be-0000-7000-8000-000000000002';

    /**
     * @test
     */
    public function it_lets_an_admin_user_save_a_pages_tree_as_a_starting_point_and_create_a_new_page_from_it(): void
    {
        $summer = self::createPageWithBlocks('Summer', 'summer', [self::heading(self::SUMMER, 'Summer sale'), self::heading(self::SHOP_NOW, 'Shop now')]);
        $summer->setBlockTree(new StoredTree(new StoredLayout('landing', 1, ['tagline' => ['en_US' => 'Up to 50% off']]), $summer->getBlockTree()->blocks));
        self::entityManager()->flush();
        $this->signInAsAdmin();

        // Save the whole tree from the entry settings
        $page = $this->visit(sprintf('/admin/cms/entry/%s/builder', $summer->getUuid()));
        $selection = $page->getByRole('complementary');
        $selection->getByRole('button', ['name' => 'Save as starting point'])->click();
        $dialog = $page->getByRole('dialog', ['name' => 'Save as starting point']);
        $dialog->getByRole('textbox', ['name' => 'Name'])->fill('Campaign page');
        $dialog->getByRole('combobox', ['name' => 'Category'])->fill('Pages');
        $dialog->getByRole('button', ['name' => 'Save preset'])->click();
        Expect::locator($dialog)->toHaveCount(0);

        // It's listed on the Presets screen's Starting points tab, for pages
        $page = $this->visit('/admin/cms/presets');
        $row = $page->locator('[data-presets] tbody tr', ['hasText' => 'Campaign page']);
        Expect::locator($row->locator('[data-content-type]'))->toHaveText('Page');

        // Create a new page from it: Blank is picked to start with, and picking it hides the layouts, as it brings its own
        $page = $this->visit('/admin/cms/entries/page');
        $page->getByRole('link', ['name' => 'Create'])->click();
        $create = $page->getByRole('dialog', ['name' => 'New Page']);
        $startingPoints = $create->getByRole('group', ['name' => 'Starting point']);
        Expect::locator($startingPoints->getByRole('radio', ['name' => 'Blank']))->toBeChecked();
        Expect::locator($create->getByRole('group', ['name' => 'Layout']))->toBeVisible();
        $startingPoints->getByText('Campaign page', ['exact' => true])->click();
        Expect::locator($startingPoints->getByRole('radio', ['name' => 'Campaign page']))->toBeChecked();
        Expect::locator($create->getByRole('group', ['name' => 'Layout']))->toBeHidden();
        $create->getByLabel('Title')->fill('Winter campaign');
        Expect::locator($create->getByLabel('Slug'))->toHaveValue('winter-campaign');
        $create->getByRole('button', ['name' => 'Create'])->click();

        // The builder opens with the same blocks, in the same layout with its tagline
        Expect::locator($page->getByRole('heading', ['level' => 1]))->toHaveText('Winter campaign');
        $tree = $page->getByRole('navigation', ['name' => 'Blocks']);
        Expect::locator($tree->getByRole('button', ['name' => 'Heading Summer sale']))->toBeVisible();
        Expect::locator($tree->getByRole('button', ['name' => 'Heading Shop now']))->toBeVisible();
        $settings = $page->getByRole('tabpanel', ['name' => 'Settings']);
        Expect::locator($settings->getByRole('radio', ['name' => 'Landing']))->toBeChecked();
        Expect::locator($page->getByLabel('Tagline'))->toHaveValue('Up to 50% off');
        $preview = $page->frameLocator('iframe[title="Preview"]');
        Expect::locator($preview->locator('.app-landing__tagline'))->toHaveText('Up to 50% off');

        $winter = self::page('winter-campaign');
        self::assertSame(['heading', 'heading'], array_map(static fn (StoredBlock $block): string => $block->type, $winter->getBlockTree()->blocks));
        self::assertNotContains($winter->getBlockTree()->blocks[0]->key, [self::SUMMER, self::SHOP_NOW]);
        self::assertSame('landing', $winter->getBlockTree()->layout?->code);
    }

    private static function page(string $slug): PageInterface
    {
        /** @var RepositoryInterface<PageInterface> $pages */
        $pages = self::getContainer()->get('setono_sylius_cms.repository.page');
        foreach ($pages->findAll() as $page) {
            if ($page->getTranslation('en_US')->getSlug() === $slug) {
                return $page;
            }
        }

        self::fail(sprintf('There is no page with the slug "%s"', $slug));
    }
}
