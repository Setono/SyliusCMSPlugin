<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\Expect;
use Setono\SyliusCMSPlugin\Tests\Support\MediaItems;

/**
 * The media link target: an editor links a Button to a document found with the search picker, and shoppers see a button
 * named by the document that leads to its permanent URL. See "Link targets" in docs/spec/extending.md and "Permanent
 * URLs" in docs/spec/media.md
 */
final class MediaLinkTest extends UITestCase
{
    use MediaItems;

    /**
     * @test
     */
    public function it_lets_an_admin_user_link_a_button_to_a_document_found_with_the_search(): void
    {
        $sizeGuide = $this->uploadMediaItem('size-guide.pdf', self::pdf());
        $this->uploadMediaItem('size-chart.png', self::png());
        $this->signInAsAdmin();
        $entry = self::createPage('Sizes', 'sizes');

        $page = $this->visit(sprintf('/admin/cms/entry/%s/builder', $entry->getUuid()));
        $page->getByRole('button', ['name' => 'Add block', 'exact' => true])->click();
        $page->getByRole('dialog', ['name' => 'Add block'])->getByRole('button', ['name' => 'Button', 'exact' => true])->click();

        // The search finds documents and videos, not images, which have no permanent URL
        $page->getByRole('combobox', ['name' => 'Link'])->selectOption('media');
        $page->getByRole('button', ['name' => 'Choose…', 'exact' => true])->click();
        $page->getByRole('combobox', ['name' => 'Search: Document or video'])->fill('size');
        $found = $page->getByRole('listbox')->getByRole('option');
        Expect::locator($found)->toHaveText('size-guide');
        $found->click();
        Expect::locator($page->getByRole('textbox', ['name' => 'Label', 'exact' => true]))->toHaveAttribute('placeholder', 'size-guide');

        $permanentUrl = sprintf('http://localhost/media/%s/size-guide.pdf', $sizeGuide->getUuid());
        Expect::locator($page->frameLocator('iframe')->locator('.cms-tree > a.cms-button'))->toHaveAttribute('href', $permanentUrl);

        Expect::locator($page->getByRole('status'))->toHaveText('Saved');
        $page->getByRole('button', ['name' => 'Publish'])->click();
        Expect::locator($page->getByText('Published', ['exact' => true]))->toBeVisible();

        $button = $this->visit('/en_US/sizes')->locator('.cms-tree > a.cms-button');
        Expect::locator($button)->toHaveText('size-guide');
        Expect::locator($button)->toHaveAttribute('href', $permanentUrl);
    }
}
