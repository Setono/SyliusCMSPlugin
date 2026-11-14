<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\Expect;
use Playwright\Network\RouteInterface;
use Playwright\Page\PageInterface as BrowserPage;
use Setono\SyliusCMSPlugin\Tests\Support\Admins;

/**
 * Two admin users edit one page at the same time, each in a browser context of their own. See "Saving" in
 * docs/spec/publishing.md and "Top bar" in docs/spec/builder.md
 */
final class ConcurrentEditingTest extends UITestCase
{
    use Admins;

    /** A colleague's change reaches a builder on its next presence beat, every five seconds */
    private const BEAT = 15000;

    /**
     * @test
     */
    public function two_admin_users_edit_one_page_see_each_other_and_each_others_changes_and_resolve_a_clash(): void
    {
        $page = self::createPageWithBlocks('About us', 'about-us', [
            self::heading('0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4a', 'Hello'),
            self::heading('0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4b', 'World'),
        ]);
        $jane = self::createAdmin('jane', 'Jane', 'Roe');
        $builderUrl = sprintf('/admin/cms/entry/%s/builder', $page->getUuid());

        $this->signInAsAdmin();
        $john = $this->visit($builderUrl);

        $janesClient = static::createPlaywrightClient();
        $janesClient->loginUser($jane, 'admin');
        $janes = $janesClient->visit($builderUrl);

        // Each sees the other in the top bar
        Expect::locator($janes->getByRole('list', ['name' => 'Also editing']))->withTimeout(self::BEAT)->toContainText('John Doe');
        Expect::locator($john->getByRole('list', ['name' => 'Also editing']))->withTimeout(self::BEAT)->toContainText('Jane Roe');

        // They edit different blocks, and each sees the other's change
        self::editHeading($john, 'Heading Hello', 'Hello there');
        self::editHeading($janes, 'Heading World', 'Wide world');

        Expect::locator($janes->getByRole('button', ['name' => 'Heading Hello there']))->withTimeout(self::BEAT)->toBeVisible();
        Expect::locator($john->getByRole('button', ['name' => 'Heading Wide world']))->withTimeout(self::BEAT)->toBeVisible();

        // They both edit the first heading before Jane's builder hears of John's change, so her save clashes. Her
        // presence beats are held back meanwhile, as if they were lost, so it can't pull his change in first
        $janesBeat = false;
        $janes->route('**/presence', static function (RouteInterface $route) use (&$janesBeat): void {
            $janesBeat ? $route->fallback() : $route->abort();
        });

        self::editHeading($john, 'Heading Hello there', "John's heading");

        $janes->getByRole('button', ['name' => 'Heading Hello there'])->click();
        $janes->getByLabel('Text')->fill("Jane's heading");

        $clash = $janes->getByRole('dialog', ['name' => 'A colleague changed this too']);
        Expect::locator($clash)->toBeVisible();
        Expect::locator($clash)->toContainText('You and a colleague both changed Text in Heading.');
        Expect::locator($clash->locator('dd')->first())->toHaveText("Jane's heading");
        Expect::locator($clash->locator('dd')->last())->toHaveText("John's heading");

        $clash->getByRole('button', ['name' => 'Keep mine'])->click();
        $janesBeat = true;

        Expect::locator($clash)->toHaveCount(0);
        Expect::locator($janes->getByLabel('Text'))->toHaveValue("Jane's heading");
        Expect::locator($janes->getByRole('status'))->toHaveText('Saved');
        Expect::locator($john->getByRole('button', ['name' => "Heading Jane's heading"]))->withTimeout(self::BEAT)->toBeVisible();

        // The working copy holds both headings as they ended up
        $saved = self::reloadPage($page)->getBlockTree();
        self::assertSame(['en_US' => "Jane's heading"], $saved->blocks[0]->fields['text'] ?? null);
        self::assertSame(['en_US' => 'Wide world'], $saved->blocks[1]->fields['text'] ?? null);
    }

    private static function editHeading(BrowserPage $builder, string $heading, string $text): void
    {
        $builder->getByRole('button', ['name' => $heading])->click();
        $builder->getByLabel('Text')->fill($text);
        Expect::locator($builder->getByRole('status'))->toHaveText('Saved');
    }
}
