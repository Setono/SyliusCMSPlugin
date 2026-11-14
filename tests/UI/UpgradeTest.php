<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\Expect;
use Setono\SyliusCMSPlugin\Tests\Functional\Fixture\UpgradesKernel;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;

/**
 * The test app's Callout block type is at version 2, where its field "text" became "message". A block saved with
 * version 1 is upgraded as the builder reads it
 */
final class UpgradeTest extends UITestCase
{
    protected static function getKernelClass(): string
    {
        return UpgradesKernel::class;
    }

    /**
     * @test
     */
    public function an_admin_user_opens_an_entry_whose_block_was_saved_with_an_older_version_and_sees_the_renamed_field_holding_its_value(): void
    {
        $this->signInAsAdmin();
        $callout = new StoredBlock('0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e60', 'callout', 1, fields: ['text' => ['en_US' => 'Saved with version 1']]);
        $page = self::createPageWithBlocks('About us', 'about-us', [$callout], published: true);

        $browser = $this->visit(sprintf('/admin/cms/entry/%s/builder', $page->getUuid()));
        $browser->getByRole('button', ['name' => 'Callout', 'exact' => false])->first()->click();

        Expect::locator($browser->getByLabel('Message'))->toHaveValue('Saved with version 1');
        Expect::locator($browser->frameLocator('iframe')->locator('.cms-tree .app-callout'))->toHaveText('Saved with version 1');
    }
}
