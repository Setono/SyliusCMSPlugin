<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\Expect;

/**
 * The Embed code block, with setono_sylius_cms.embed_code on in the test application: its code's script never runs in
 * the builder, where its preview shows a placeholder, and runs in the shop once it's published.
 * See "Embed code" in docs/spec/blocks.md and ADR 0018
 */
final class EmbedCodeTest extends UITestCase
{
    private const CODE = '<div id="reviews">Reviews widget</div><script>document.documentElement.dataset.embedMarker = "set";</script>';

    /**
     * @test
     */
    public function its_script_runs_in_the_shop_after_publishing_but_never_in_the_builder_where_a_placeholder_stands_in(): void
    {
        $this->signInAsAdmin();
        $page = self::createPage('Reviews', 'reviews');

        $browser = $this->visit(sprintf('/admin/cms/entry/%s/builder', $page->getUuid()));
        $browser->getByRole('button', ['name' => 'Add block', 'exact' => true])->click();
        $browser->getByRole('dialog', ['name' => 'Add block'])->getByRole('button', ['name' => 'Embed code', 'exact' => true])->click();
        $browser->getByLabel('Code')->fill(self::CODE);

        Expect::locator($browser->getByRole('status'))->toHaveText('Saved');

        // The preview shows the placeholder in its place, and the script didn't run in the preview or in the builder
        $preview = $browser->frameLocator('iframe');
        Expect::locator($preview->locator('.cms-tree > .cms-embed-code.cms-embed-code--placeholder'))->toHaveText('Embed code runs in the shop, not in the builder\'s preview.');
        Expect::locator($preview->locator('#reviews'))->toHaveCount(0);
        Expect::locator($preview->locator('html'))->not()->toHaveAttribute('data-embed-marker', 'set');
        Expect::locator($browser->locator('html'))->not()->toHaveAttribute('data-embed-marker', 'set');

        $browser->getByRole('button', ['name' => 'Publish'])->click();
        Expect::locator($browser->getByText('Published', ['exact' => true]))->toBeVisible();

        $shop = $this->visit('/en_US/reviews');

        Expect::locator($shop->locator('.cms-tree > .cms-embed-code > #reviews'))->toHaveText('Reviews widget');
        Expect::locator($shop->locator('html'))->toHaveAttribute('data-embed-marker', 'set');
        Expect::locator($shop->locator('.cms-embed-code--placeholder'))->toHaveCount(0);
    }
}
