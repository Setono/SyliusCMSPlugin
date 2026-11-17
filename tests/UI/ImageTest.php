<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\Expect;
use Setono\SyliusCMSPlugin\Tests\Support\MediaItems;

/**
 * The Image block: an editor adds one, sees a grey placeholder in the preview until an image is picked from the media
 * library, and shoppers see the published image responsively, with a srcset of variants LiipImagine makes on their first
 * request, loaded lazily. See "Image" and "Empty images" in docs/spec/blocks.md and "The image helper" in
 * docs/spec/media.md
 */
final class ImageTest extends UITestCase
{
    use MediaItems;

    /**
     * @test
     */
    public function it_lets_an_admin_user_add_an_image_block_pick_an_image_and_publish_it_for_shoppers_as_a_responsive_lazy_image(): void
    {
        $image = $this->uploadMediaItem('summer-sale.jpg', self::jpeg(1200, 800));
        $this->signInAsAdmin();
        $entry = self::createPage('Summer', 'summer');

        $page = $this->visit(sprintf('/admin/cms/entry/%s/builder', $entry->getUuid()));
        $page->getByRole('button', ['name' => 'Add block', 'exact' => true])->click();
        $page->getByRole('dialog', ['name' => 'Add block'])->getByRole('button', ['name' => 'Image', 'exact' => true])->click();

        // Until an image is picked, the preview shows a grey box where it goes
        $preview = $page->frameLocator('iframe[title="Preview"]');
        $placeholder = $preview->locator('.cms-tree > figure.cms-image > .cms-image-placeholder');
        Expect::locator($placeholder)->toBeVisible();
        self::assertSame('rgb(224, 224, 224)', $placeholder->evaluate('box => getComputedStyle(box).backgroundColor'));

        // The field's button opens the media library as a picker of images
        $page->getByLabel('Image', ['exact' => true])->click();
        $picker = $page->getByRole('dialog', ['name' => 'Choose from the media library']);
        $picker->locator('[data-media-item]')->filter(['hasText' => 'summer-sale'])->click();
        $picker->getByRole('button', ['name' => 'Choose', 'exact' => true])->click();
        Expect::locator($picker)->toHaveCount(0);

        Expect::locator($preview->locator('.cms-tree > figure.cms-image img'))->toBeVisible();
        Expect::locator($placeholder)->toHaveCount(0);

        Expect::locator($page->getByRole('status'))->toHaveText('Saved');
        $page->getByRole('button', ['name' => 'Publish'])->click();
        Expect::locator($page->getByText('Published', ['exact' => true]))->toBeVisible();

        $shop = $this->visit('/en_US/summer');
        $img = $shop->locator('.cms-tree > figure.cms-image img');
        Expect::locator($img)->toHaveAttribute('loading', 'lazy');
        Expect::locator($img)->toHaveAttribute('width', '1200');
        $srcset = (string) $img->getAttribute('srcset');
        self::assertMatchesRegularExpression('~^/media/cache/resolve/setono_sylius_cms_webp/rc/[^ ]+/' . preg_quote($image->getPath(), '~') . '\?\S+ 320w, ~', $srcset);
        self::assertStringEndsWith(' 1200w', $srcset);

        // The browser gets an image from the variant it picks, which LiipImagine makes on its first request and redirects
        // to. playwright-symfony follows a redirect for a fetch but not for an <img>, so the variant is fetched
        Expect::locator($img)->toHaveJSProperty('complete', true);
        $width = $img->evaluate('async (img) => createImageBitmap(await (await fetch(img.currentSrc)).blob()).then((variant) => variant.width)');
        self::assertIsInt($width);
        self::assertGreaterThan(0, $width);
    }
}
