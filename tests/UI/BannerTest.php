<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\Expect;
use Playwright\Locator\LocatorInterface;
use Setono\SyliusCMSPlugin\Tests\Support\MediaItems;

/**
 * The Banner block: an editor adds a full-width Banner, which comes with its first Button, picks its image, types its
 * heading and links its Button, and shoppers see it reach the edges of the viewport, out of the shop's container, with
 * the heading and the button over the image. See "Banner" and "Class names" in docs/spec/blocks.md and "Shipped layouts"
 * in docs/spec/layouts-and-presets.md
 */
final class BannerTest extends UITestCase
{
    use MediaItems;

    /**
     * @test
     */
    public function it_lets_an_admin_user_publish_a_full_width_banner_with_an_image_a_heading_and_a_button_that_reaches_the_edges_of_the_viewport(): void
    {
        $this->uploadMediaItem('summer-sale.jpg', self::jpeg(1600, 800));
        $this->signInAsAdmin();
        $entry = self::createPage('Summer', 'summer');

        $page = $this->visit(sprintf('/admin/cms/entry/%s/builder', $entry->getUuid()));
        $tree = $page->getByRole('navigation', ['name' => 'Blocks']);
        $page->getByRole('button', ['name' => 'Add block', 'exact' => true])->click();
        $page->getByRole('dialog', ['name' => 'Add block'])->getByRole('button', ['name' => 'Banner', 'exact' => true])->click();

        // A Banner holds only Buttons, so it comes with its first one
        Expect::locator($tree->getByRole('button', ['name' => 'Button', 'exact' => true]))->toHaveCount(1);

        // Until an image is picked, the preview shows a grey box where it goes
        $preview = $page->frameLocator('iframe[title="Preview"]');
        Expect::locator($preview->locator('.cms-tree > .cms-banner > .cms-image-placeholder.cms-banner__media'))->toBeVisible();

        $page->getByLabel('Image', ['exact' => true])->click();
        $picker = $page->getByRole('dialog', ['name' => 'Choose from the media library']);
        $picker->locator('[data-media-item]')->filter(['hasText' => 'summer-sale'])->click();
        $picker->getByRole('button', ['name' => 'Choose', 'exact' => true])->click();
        Expect::locator($picker)->toHaveCount(0);

        $page->getByRole('textbox', ['name' => 'Heading', 'exact' => true])->fill('Summer sale');
        $page->getByLabel('Full width', ['exact' => true])->check();
        Expect::locator($preview->locator('.cms-tree > .cms-banner.cms-full-width > picture.cms-banner__media img'))->toBeVisible();
        Expect::locator($preview->locator('.cms-tree > .cms-banner .cms-banner__heading'))->toHaveText('Summer sale');

        $tree->getByRole('button', ['name' => 'Button', 'exact' => true])->click();
        $page->getByRole('combobox', ['name' => 'Link'])->selectOption('url');
        $page->getByRole('textbox', ['name' => 'URL'])->fill('/en_US/sale');
        $page->getByRole('textbox', ['name' => 'Label', 'exact' => true])->fill('Shop now');
        Expect::locator($preview->locator('.cms-tree > .cms-banner .cms-banner__buttons > a.cms-button'))->toHaveText('Shop now');

        Expect::locator($page->getByRole('status'))->toHaveText('Saved');
        $page->getByRole('button', ['name' => 'Publish'])->click();
        Expect::locator($page->getByText('Published', ['exact' => true]))->toBeVisible();

        $shop = $this->visit('/en_US/summer');
        $shop->setViewportSize(1280, 800);
        $banner = $shop->locator('.cms-tree > .cms-banner.cms-full-width');
        Expect::locator($banner->locator('.cms-banner__content .cms-banner__heading'))->toHaveText('Summer sale');
        Expect::locator($banner->locator('.cms-banner__buttons > a.cms-button'))->toHaveAttribute('href', '/en_US/sale');

        // The banner breaks out of the shop's container, which is narrower than the viewport, to both its edges
        $viewportWidth = $shop->evaluate('() => document.documentElement.clientWidth');
        self::assertIsInt($viewportWidth);
        $container = self::box($shop->locator('.cms-tree'));
        self::assertLessThan($viewportWidth, $container['width'], 'The tree sits in the shop\'s container');
        $box = self::box($banner);
        self::assertEqualsWithDelta(0, $box['x'], 1.0);
        self::assertEqualsWithDelta($viewportWidth, $box['width'], 1.0);
        self::assertSame(0, $shop->evaluate('() => document.documentElement.scrollWidth - document.documentElement.clientWidth'), 'The page doesn\'t scroll sideways');

        // The image covers the whole banner, at least --cms-banner-min-height tall, and the content sits over it on the shade
        self::assertGreaterThanOrEqual(360, $box['height']);
        $image = self::box($banner->locator('picture.cms-banner__media img'));
        self::assertEqualsWithDelta([$box['x'], $box['y'], $box['width'], $box['height']], [$image['x'], $image['y'], $image['width'], $image['height']], 1.0);
        $content = self::box($banner->locator('.cms-banner__content'));
        self::assertEqualsWithDelta([$box['x'], $box['y'], $box['width'], $box['height']], [$content['x'], $content['y'], $content['width'], $content['height']], 1.0);
        self::assertSame('rgba(0, 0, 0, 0.35)', $banner->locator('.cms-banner__content')->evaluate('content => getComputedStyle(content).backgroundColor'));
    }

    /**
     * @return array{x: float|int, y: float|int, width: float|int, height: float|int}
     */
    private static function box(LocatorInterface $locator): array
    {
        $box = $locator->boundingBox();
        self::assertNotNull($box);

        return $box;
    }
}
