<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Doctrine\Persistence\ObjectManager;
use Playwright\Assertions\Expect;
use Setono\SyliusCMSPlugin\Tests\Functional\Fixture\CampaignLayoutKernel;
use Setono\SyliusCMSPlugin\Tree\StoredLayout;
use Setono\SyliusCMSPlugin\Tree\StoredTree;

/**
 * Publish refuses an empty required layout field and a visibility condition whose parameters don't fit, and the builder
 * shows each error where it is, and the entry's overview page lists the broken references and removed kinds in its
 * conditions. See "Validation" in docs/spec/publishing.md and "Problems" in docs/spec/admin.md
 */
final class LayoutFieldAndConditionValidationTest extends UITestCase
{
    private const HEADING = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f';

    private const OTHER_HEADING = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e50';

    protected static function getKernelClass(): string
    {
        return CampaignLayoutKernel::class;
    }

    /**
     * @test
     */
    public function it_shows_an_empty_required_layout_field_as_an_error_under_it_refuses_to_publish_and_publishes_once_it_is_filled_in(): void
    {
        $this->signInAsAdmin();
        $page = self::createPage('Winter sale', 'winter-sale', 'Hello');
        $page->setBlockTree(new StoredTree(new StoredLayout('campaign', 1), $page->getBlockTree()->blocks));
        self::flush();

        $browser = $this->visit(sprintf('/admin/cms/entry/%s/builder', $page->getUuid()));
        $settings = $browser->getByRole('tabpanel', ['name' => 'Settings']);

        // The builder validates what it opens
        $headline = $settings->getByRole('textbox', ['name' => 'Headline']);
        Expect::locator($headline)->toHaveAttribute('aria-describedby', 'cms-layout-field-headline-errors');
        Expect::locator($settings->locator('#cms-layout-field-headline-errors'))->toHaveText('This field is required.');

        $browser->getByRole('button', ['name' => 'Publish'])->click();
        Expect::locator($browser->getByRole('alert'))->toHaveText("Couldn't publish: One error needs fixing");
        Expect::locator($browser->getByText('Never published', ['exact' => true]))->toBeVisible();

        $headline->fill('Up to 50% off');
        Expect::locator($browser->getByRole('status'))->toHaveText('Saved');
        Expect::locator($settings->locator('#cms-layout-field-headline-errors'))->toHaveCount(0);

        $browser->getByRole('button', ['name' => 'Publish'])->click();
        Expect::locator($browser->getByText('Published', ['exact' => true]))->toBeVisible();
    }

    /**
     * @test
     */
    public function it_shows_a_date_window_that_ends_before_it_starts_as_an_error_at_its_end_and_publishes_once_it_is_fixed(): void
    {
        $this->signInAsAdmin();
        $heading = self::heading(self::HEADING, 'Christmas offers');
        $heading->conditions = [['kind' => 'date_window', 'negate' => false, 'params' => ['from' => '2026-12-24T00:00:00Z', 'until' => '2026-12-01T00:00:00Z']]];
        $page = self::createPageWithBlocks('Christmas', 'christmas', [$heading]);

        $browser = $this->visit(sprintf('/admin/cms/entry/%s/builder', $page->getUuid()));
        $tree = $browser->getByRole('navigation', ['name' => 'Blocks']);
        Expect::locator($tree->getByRole('img', ['name' => 'Has errors']))->toHaveCount(1);

        // Publish refuses, and selects the heading, whose condition shows the error at its end
        $browser->getByRole('button', ['name' => 'Publish'])->click();
        Expect::locator($browser->getByRole('alert'))->toHaveText("Couldn't publish: One error needs fixing");
        Expect::locator($tree->getByRole('button', ['name' => 'Heading Christmas offers']))->toHaveAttribute('aria-pressed', 'true');
        $condition = $browser->getByRole('complementary')->getByRole('group', ['name' => 'Condition 1']);
        $until = $condition->locator(sprintf('#cms-condition-%s-0-until', self::HEADING));
        $errorsId = sprintf('cms-condition-%s-0-until-errors', self::HEADING);
        Expect::locator($condition->locator('#' . $errorsId))->toHaveText('Until must come after From.');
        // Its error describes it, then the time zone it's entered in
        Expect::locator($until)->toHaveAccessibleDescription(sprintf('Until must come after From. %s', date_default_timezone_get()));

        // Ending it after it starts clears the error, and publish goes through
        $until->fill('2027-01-01T00:00');
        Expect::locator($browser->getByRole('status'))->toHaveText('Saved');
        Expect::locator($tree->getByRole('img', ['name' => 'Has errors']))->toHaveCount(0);
        Expect::locator($condition->locator('#' . $errorsId))->toHaveCount(0);

        $browser->getByRole('button', ['name' => 'Publish'])->click();
        Expect::locator($browser->getByText('Published', ['exact' => true]))->toBeVisible();
    }

    /**
     * @test
     */
    public function it_lists_the_broken_references_and_the_removed_kinds_in_conditions_on_the_overview_page_and_fixes_them_in_the_builder(): void
    {
        $this->signInAsAdmin();
        $heading = self::heading(self::HEADING, 'Outlet');
        $heading->conditions = [['kind' => 'channel', 'negate' => false, 'params' => ['channels' => ['WEB', 'OUTLET']]]];
        $other = self::heading(self::OTHER_HEADING, 'Members');
        $other->conditions = [
            ['kind' => 'product', 'negate' => false, 'params' => ['products' => ['HAT']]],
            ['kind' => 'app_signed_in', 'negate' => false, 'params' => []],
        ];
        $page = self::createPageWithBlocks('Outlet', 'outlet', [$heading, $other], published: true);

        $browser = $this->visit(sprintf('/admin/cms/entry/%s', $page->getUuid()));

        $problems = $browser->getByRole('region', ['name' => 'Problems']);
        self::assertTexts([
            'The channel "OUTLET" in a condition of the Heading block was deleted, so the condition no longer counts it.',
            'Products in a condition of the Heading block refers to a product that was deleted, so the condition no longer counts it.',
            'A condition of the Heading block is of the kind "app_signed_in", which the code no longer has, so the block isn\'t shown.',
        ], $problems->locator('[data-problem] .description'));
        self::assertTexts(['Error', 'Error', 'Warning'], $problems->locator('[data-problem] .header'));

        // The removed kind's fix opens the builder at its block, where the condition shows what's wrong
        $problems->locator('[data-problem="unknown_condition_kind"]')->getByRole('link', ['name' => 'Fix in builder'])->click();
        $tree = $browser->getByRole('navigation', ['name' => 'Blocks']);
        Expect::locator($tree->getByRole('button', ['name' => 'Heading Members']))->toHaveAttribute('aria-pressed', 'true');
        $condition = $browser->getByRole('complementary')->getByRole('group', ['name' => 'Condition 2']);
        Expect::locator($condition->getByText('The condition kind "app_signed_in" no longer exists, so the block isn\'t shown. Remove the condition.'))->toBeVisible();
    }

    private static function flush(): void
    {
        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('setono_sylius_cms.manager.page');
        $manager->flush();
    }
}
