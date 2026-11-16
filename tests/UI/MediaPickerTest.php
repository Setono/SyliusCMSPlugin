<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\Expect;
use Setono\SyliusCMSPlugin\Tests\Functional\Fixture\MediaFieldsKernel;
use Setono\SyliusCMSPlugin\Tests\Support\MediaItems;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;

/**
 * A media field in the builder opens the media library as a picker, where an editor uploads an image and picks it, and
 * the preview shows it. See "Media library" in docs/spec/admin.md and "Uploading" in docs/spec/media.md
 */
final class MediaPickerTest extends UITestCase
{
    use MediaItems;

    private const BLOCK = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e70';

    private ?string $uploadDirectory = null;

    protected static function getKernelClass(): string
    {
        return MediaFieldsKernel::class;
    }

    /**
     * @test
     */
    public function it_lets_an_editor_upload_an_image_from_inside_a_media_fields_picker_pick_it_and_see_its_name_in_the_preview(): void
    {
        $this->signInAsAdmin();
        $page = self::createPageWithBlocks('Gallery', 'gallery', [new StoredBlock(self::BLOCK, 'media_names', 1)]);

        $browser = $this->visit(sprintf('/admin/cms/entry/%s/builder', $page->getUuid()));
        $browser->getByRole('navigation', ['name' => 'Blocks'])->getByRole('button', ['name' => 'Media names', 'exact' => false])->first()->click();
        Expect::locator($browser->frameLocator('iframe')->locator('.cms-tree .app-media-names__image'))->toHaveText('No image');
        Expect::locator($browser->getByText('Nothing chosen')->first())->toBeVisible();

        // The field's button opens the library as a picker of images
        $browser->getByRole('button', ['name' => 'Image', 'exact' => true])->click();
        $picker = $browser->getByRole('dialog', ['name' => 'Choose from the media library']);
        Expect::locator($picker->getByRole('navigation', ['name' => 'Folders']))->toBeVisible();
        Expect::locator($picker->getByRole('combobox', ['name' => 'Kind']))->toBeDisabled();
        Expect::locator($picker->getByRole('combobox', ['name' => 'Kind']))->toHaveValue('image');

        // An image uploaded inside it is picked
        $picker->locator('input[type="file"]')->setInputFiles($this->fileToUpload('summer-sale.png', self::png(80, 40)));
        $uploaded = $picker->locator('[data-media-item]')->filter(['hasText' => 'summer-sale']);
        Expect::locator($uploaded)->toHaveAttribute('aria-pressed', 'true');
        $picker->getByRole('button', ['name' => 'Choose', 'exact' => true])->click();

        Expect::locator($picker)->toHaveCount(0);
        Expect::locator($browser->getByRole('button', ['name' => 'Image', 'exact' => true]))->toHaveText('Replace');
        Expect::locator($browser->getByText('summer-sale', ['exact' => true]))->toBeVisible();
        Expect::locator($browser->getByRole('status'))->toHaveText('Saved');
        Expect::locator($browser->frameLocator('iframe')->locator('.cms-tree .app-media-names__image'))->toHaveText('summer-sale');
    }

    /**
     * @after
     */
    protected function removeFileToUpload(): void
    {
        if (null === $this->uploadDirectory) {
            return;
        }

        foreach ((array) glob($this->uploadDirectory . '/*') as $file) {
            unlink((string) $file);
        }

        rmdir($this->uploadDirectory);
        $this->uploadDirectory = null;
    }

    /**
     * A file with this name on disk, as an editor picks it in the browser's file dialog
     */
    private function fileToUpload(string $filename, string $content): string
    {
        $this->uploadDirectory = sys_get_temp_dir() . '/setono_sylius_cms_ui_' . bin2hex(random_bytes(4));
        mkdir($this->uploadDirectory);
        file_put_contents($this->uploadDirectory . '/' . $filename, $content);

        return $this->uploadDirectory . '/' . $filename;
    }
}
