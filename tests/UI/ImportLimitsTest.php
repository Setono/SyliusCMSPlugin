<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\AssertionOptions;
use Playwright\Assertions\Expect;
use Setono\SyliusCMSPlugin\Tests\Functional\Fixture\SmallImportLimitsKernel;
use Setono\SyliusCMSPlugin\Tests\Support\Imports;
use Setono\SyliusCMSPlugin\Tests\Support\SentMessages;
use Setono\SyliusCMSPlugin\Tests\Support\TemporaryDirectory;

/**
 * An import started in the admin takes media files of up to transfer.max_file_size, 1 KB here. The Messenger worker's
 * dry run refuses an export with a bigger one, and the import's page says which and why. See "Media files" in
 * docs/spec/transfer.md
 */
final class ImportLimitsTest extends UITestCase
{
    use Imports;
    use SentMessages;
    use TemporaryDirectory;

    protected static function getKernelClass(): string
    {
        return SmallImportLimitsKernel::class;
    }

    /**
     * @test
     */
    public function it_shows_an_admin_user_why_an_import_of_a_media_file_over_the_limit_refused(): void
    {
        self::forgetSentMessages();
        $this->signInAsAdmin();
        $export = $this->copyOfFixture();
        self::growVideoTo($export, 1025);

        $browser = $this->visit('/admin/cms/transfer');
        $import = $browser->getByRole('region', ['name' => 'Import']);
        $import->getByLabel('Export zip')->setInputFiles($this->zipOf($export));
        $import->getByRole('button', ['name' => 'Upload'])->click();

        Expect::locator($browser->locator('[data-cms-import-progress]'))->toContainText('Waiting for the Messenger worker to check the export.');
        self::assertSame(1, self::consumeMessages());

        Expect::locator($browser->locator('[data-cms-import-result]'))->toContainText('The import refused. Nothing changed.', new AssertionOptions(timeoutMs: 15_000));
        Expect::locator($browser->locator('[data-cms-import-reasons]'))->toContainText('The media item lookbook can\'t be imported: lookbook.mp4 is over the import limit of 1 KB per file.');
        Expect::locator($browser->locator('form[data-cms-import-confirm]'))->toHaveCount(0);
    }
}
