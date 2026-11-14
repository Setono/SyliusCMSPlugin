<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\Expect;

final class PreviewTest extends UITestCase
{
    /**
     * @test
     */
    public function an_admin_user_types_a_heading_and_sees_it_in_the_preview(): void
    {
        $this->signInAsAdmin();
        $entry = self::createPage('About us', 'about-us');

        $page = $this->visit(sprintf('/admin/cms/entry/%s/builder', $entry->getUuid()));
        $preview = $page->frameLocator('iframe[title="Preview"]');

        // The page was never published, yet the preview shows it in the shop layout
        Expect::locator($preview->getByRole('heading', ['level' => 1]))->toHaveText('About us');
        Expect::locator($preview->locator('#footer'))->toBeAttached();

        $page->getByRole('button', ['name' => 'Add block', 'exact' => true])->click();
        $page->getByRole('dialog', ['name' => 'Add block'])->getByRole('button', ['name' => 'Heading', 'exact' => true])->click();
        $page->getByLabel('Text')->fill('Hello');

        Expect::locator($page->getByRole('status'))->toHaveText('Saved');
        Expect::locator($preview->locator('.cms-tree .cms-heading'))->toHaveText('Hello');
    }

    /**
     * @test
     */
    public function an_admin_user_switches_the_preview_to_mobile_and_sees_it_narrow(): void
    {
        $this->signInAsAdmin();
        $entry = self::createPage('About us', 'about-us', 'Hello');

        $page = $this->visit(sprintf('/admin/cms/entry/%s/builder', $entry->getUuid()));
        $frame = $page->locator('iframe[title="Preview"]');
        Expect::locator($frame->contentFrame()->locator('.cms-heading'))->toHaveText('Hello');
        $desktopWidth = $frame->boundingBox()['width'] ?? null;
        self::assertIsNumeric($desktopWidth);

        $page->getByRole('button', ['name' => 'Mobile'])->click();

        Expect::locator($page->getByRole('button', ['name' => 'Mobile']))->toHaveAttribute('aria-pressed', 'true');
        Expect::locator($frame)->toHaveCSS('width', '375px');
        self::assertLessThan($desktopWidth, $frame->boundingBox()['width'] ?? null);

        $page->getByRole('button', ['name' => 'Desktop'])->click();

        Expect::locator($frame)->toHaveCSS('width', sprintf('%dpx', $desktopWidth));
    }
}
