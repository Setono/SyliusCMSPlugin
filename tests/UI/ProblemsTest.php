<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\Expect;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;

/**
 * The errors and warnings the admin flags: the lists' problem marker, the Content badge and the overview page's
 * Problems section, whose fix opens the builder at the block. See "Problems" in docs/spec/admin.md
 */
final class ProblemsTest extends UITestCase
{
    private const PROMO = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e50';

    /**
     * @test
     */
    public function it_shows_a_block_of_an_unknown_type_as_the_marker_in_pages_the_problem_on_the_overview_page_and_a_badge_on_content(): void
    {
        $this->signInAsAdmin();
        $page = self::createPageWithBlocks('About us', 'about-us', [self::heading('0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f', 'Hello'), new StoredBlock(self::PROMO, 'promo', 1)]);

        $browser = $this->visit('/admin/cms/entries/page');

        // The Content item's badge, an orange label with the count, as only a warning needs attention
        $content = $browser->locator('.sylius-admin-menu a[href="/admin/cms"]');
        Expect::locator($content)->toHaveAttribute('title', 'No errors, 1 warning');
        self::assertSame('"1"', $content->evaluate('element => getComputedStyle(element, "::after").content'));
        self::assertSame('rgb(242, 113, 28)', $content->evaluate('element => getComputedStyle(element, "::after").backgroundColor'));

        // The marker in Pages leads to the problem on the overview page
        $marker = $browser->locator('tbody [data-column="problems"]')->getByRole('link');
        Expect::locator($marker)->toHaveAttribute('title', 'No errors, 1 warning');
        self::assertSame('1', trim((string) $marker->textContent()));
        $marker->click();

        $problems = $browser->getByRole('region', ['name' => 'Problems']);
        Expect::locator($problems)->toContainText('Warning');
        Expect::locator($problems)->toContainText('A block of the type "promo", which the code no longer has, isn\'t shown.');
        self::assertStringEndsWith(sprintf('/admin/cms/entry/%s#cms-section-problems', $page->getUuid()), $browser->url());

        // Its fix opens the builder with the block selected, where it can be removed
        $problems->getByRole('link', ['name' => 'Fix in builder'])->click();
        $tree = $browser->getByRole('navigation', ['name' => 'Blocks']);
        Expect::locator($tree->getByRole('button', ['name' => 'Unknown block type: promo']))->toHaveAttribute('aria-pressed', 'true');
        Expect::locator($browser->getByText('The code no longer has this block type.', ['exact' => false]))->toBeVisible();
    }
}
