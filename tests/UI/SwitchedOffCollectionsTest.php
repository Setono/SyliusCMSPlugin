<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\Expect;
use Setono\SyliusCMSPlugin\Tests\Functional\Fixture\SwitchedOffCollectionsKernel;

/**
 * The Blog and the FAQ switched off as a whole (SwitchedOffCollectionsKernel), as a project that has neither does: the
 * content hub, the create dialog and the builder offer neither. See "What ships" in docs/spec/collections.md
 */
final class SwitchedOffCollectionsTest extends UITestCase
{
    protected static function getKernelClass(): string
    {
        return SwitchedOffCollectionsKernel::class;
    }

    /**
     * @test
     */
    public function it_offers_an_admin_user_neither_the_blog_nor_the_faq_in_the_hub_the_create_dialog_or_the_builder(): void
    {
        $this->signInAsAdmin();

        // The content hub's menu lists the other collections only
        $browser = $this->visit('/admin/cms/entries');
        $menu = $browser->locator('[data-hub-menu]');
        Expect::locator($menu->getByRole('link', ['name' => 'Announcements']))->toBeVisible();
        Expect::locator($menu->getByRole('link', ['name' => 'Blog']))->toHaveCount(0);
        Expect::locator($menu->getByRole('link', ['name' => 'FAQ']))->toHaveCount(0);
        Expect::locator($menu->getByRole('link', ['name' => 'Categories']))->toHaveCount(0);

        // The create dialog doesn't offer their content types
        $browser->getByRole('button', ['name' => 'Create'])->click();
        $dialog = $browser->getByRole('dialog');
        Expect::locator($dialog->getByRole('link', ['name' => 'Page']))->toBeVisible();
        foreach (['Blog post', 'Blog category', 'FAQ item', 'FAQ category'] as $contentType) {
            Expect::locator($dialog->getByRole('link', ['name' => $contentType]))->toHaveCount(0);
        }

        // Nor does From a collection in the builder's Entries block
        $entry = self::createPage('Stores', 'stores');
        $page = $this->visit(sprintf('/admin/cms/entry/%s/builder', $entry->getUuid()));
        $page->getByRole('button', ['name' => 'Add block', 'exact' => true])->click();
        $page->getByRole('dialog', ['name' => 'Add block'])->getByRole('button', ['name' => 'Entries', 'exact' => true])->click();
        $fields = $page->getByRole('complementary');
        $fields->getByRole('combobox', ['name' => 'Filled by'])->selectOption('entries_from_collection');
        self::assertTexts(['Announcements', 'Store locations'], $fields->getByRole('combobox', ['name' => 'Collection'])->locator('option:not([value=""])'));
    }
}
