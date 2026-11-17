<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\Expect;
use Playwright\Locator\LocatorInterface;
use Setono\SyliusCMSPlugin\Tests\Functional\Fixture\MediaFieldsKernel;
use Setono\SyliusCMSPlugin\Tests\Support\MediaItems;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;

/**
 * An editor replaces an image that a published page uses, after the dialog lists the page, and the shop shows the new
 * image at once, without the page being published again. The image can't be deleted while the page uses it, and the
 * library says where. See "Replacing a file", "Usage index" and "Deleting" in docs/spec/media.md and ADR 0013
 */
final class MediaUsageTest extends UITestCase
{
    use MediaItems;

    private const BLOCK = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e70';

    protected static function getKernelClass(): string
    {
        return MediaFieldsKernel::class;
    }

    /**
     * @test
     */
    public function it_lets_an_editor_replace_an_image_a_published_page_uses_which_the_shop_shows_at_once_and_not_delete_it(): void
    {
        $this->signInAsAdmin();
        $image = $this->uploadMediaItem('summer-sale.png', self::png(80, 40, [200, 30, 30]));
        self::createPageWithBlocks('Summer sale', 'summer-sale', [new StoredBlock(self::BLOCK, 'media_names', 1, fields: ['image' => $image->getUuid()])], published: true);
        $oldSrc = '/media/cms/' . $image->getPath();

        $shop = $this->visit('/en_US/summer-sale');
        Expect::locator($shop->locator('.cms-tree .app-media-names__file'))->toHaveAttribute('src', $oldSrc);

        // The library counts the use, and names the page
        $browser = $this->visit('/admin/cms/media');
        $item = $browser->locator('[data-media-item]')->filter(['hasText' => 'summer-sale']);
        Expect::locator($item)->toContainText('1 use');
        $item->click();
        $details = $browser->getByRole('complementary', ['name' => 'summer-sale']);
        $usedIn = $details->getByRole('list', ['name' => 'Used in']);
        Expect::locator($usedIn->getByRole('link', ['name' => 'Summer sale']))->toBeVisible();
        Expect::locator($usedIn)->toContainText('Page · Published');

        // Delete is disabled while the page uses it
        Expect::locator($details->getByRole('button', ['name' => 'Delete']))->toBeDisabled();
        Expect::locator($details->getByText('It can\'t be deleted while it\'s used.'))->toBeVisible();

        // Replace file lists the page and says the change takes effect at once
        $details->getByRole('button', ['name' => 'Replace file'])->click();
        $dialog = $browser->getByRole('dialog', ['name' => 'Replace file']);
        Expect::locator($dialog)->toContainText('The new file takes effect at once wherever it\'s used, without publishing again.');
        Expect::locator($dialog->getByRole('link', ['name' => 'Summer sale']))->toBeVisible();
        $dialog->getByLabel('New file')->setInputFiles($this->fileToUpload('summer-sale-2027.png', self::png(120, 60, [30, 160, 60])));
        $dialog->getByRole('button', ['name' => 'Replace', 'exact' => true])->click();

        Expect::locator($dialog)->toHaveCount(0);
        Expect::locator($details->getByRole('link', ['name' => 'summer-sale-2027.png']))->toBeVisible();
        Expect::locator($details->getByRole('button', ['name' => 'Delete']))->toBeDisabled();

        // The shop shows the new image without the page being published again
        $shop = $this->visit('/en_US/summer-sale');
        $file = $shop->locator('.cms-tree .app-media-names__file');
        $src = $file->getAttribute('src');
        self::assertIsString($src);
        self::assertNotSame($oldSrc, $src);
        self::assertStringStartsWith('/media/cms/' . $image->getUuid() . '/', $src);
        self::assertStringEndsWith('/summer-sale-2027.png', $src);
        self::assertSame(120, self::naturalWidth($file));
    }

    /**
     * The width of the image the browser loaded, once it has
     */
    private static function naturalWidth(LocatorInterface $image): int
    {
        $width = $image->evaluate(<<<'JS'
            (image) => image.complete
                ? image.naturalWidth
                : new Promise((resolve) => {
                    image.addEventListener('load', () => resolve(image.naturalWidth));
                    image.addEventListener('error', () => resolve(0));
                })
            JS);
        self::assertIsInt($width);

        return $width;
    }
}
