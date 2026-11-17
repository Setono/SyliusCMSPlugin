<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\Expect;
use Playwright\Page\PageInterface;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;

/**
 * The Carousel block in the shop, where the carousel script adds its buttons and dots to the scroll-snap track, and in
 * the builder's preview, where the script initialises a carousel again once the bridge patches it in. See "Carousel" in
 * docs/spec/blocks.md and "The bridge" in docs/spec/builder.md
 */
final class CarouselTest extends UITestCase
{
    private const CAROUSEL = '0193a5be-0000-7000-8000-0000000000a1';

    /**
     * The slides that lie wholly inside the track's box, in the page's main carousel
     */
    private const SHOWN_SLIDES = <<<'JS'
        () => {
            const track = document.querySelector('.cms-tree .cms-carousel__track');
            const box = track.getBoundingClientRect();

            return Array.from(track.querySelectorAll(':scope > .cms-carousel__slide'))
                .filter((slide) => {
                    const slideBox = slide.getBoundingClientRect();

                    return slideBox.left >= box.left - 1 && slideBox.right <= box.right + 1;
                })
                .map((slide) => slide.textContent.trim());
        }
        JS;

    /**
     * @test
     */
    public function it_shows_a_carousel_of_three_headings_one_at_a_time_in_the_shop_and_moves_to_the_next_with_its_button(): void
    {
        self::createPageWithBlocks('Campaign', 'campaign', [self::carousel([
            self::heading('0193a5be-0000-7000-8000-0000000000a2', 'Summer sale'),
            self::heading('0193a5be-0000-7000-8000-0000000000a3', 'Free shipping'),
            self::heading('0193a5be-0000-7000-8000-0000000000a4', 'New in'),
        ])], published: true);

        $shop = $this->visit('/en_US/campaign');
        $carousel = $shop->locator('.cms-tree > .cms-carousel');

        // The script has added a dot per slide, the first one current
        $dots = $carousel->locator('.cms-carousel__dots .cms-carousel__dot');
        Expect::locator($dots)->toHaveCount(3);
        Expect::locator($dots->nth(0))->toHaveAttribute('aria-current', 'true');
        Expect::locator($dots->nth(0))->toHaveAccessibleName('Slide 1');
        self::assertSame(['Summer sale'], $shop->evaluate(self::SHOWN_SLIDES), 'One slide shows at a time');

        $carousel->getByRole('button', ['name' => 'Next slide'])->click();

        self::waitForShownSlides($shop, ['Free shipping']);
        Expect::locator($dots->nth(1))->toHaveAttribute('aria-current', 'true');
        Expect::locator($dots->nth(0))->not()->toHaveAttribute('aria-current', 'true');

        $carousel->getByRole('button', ['name' => 'Slide 3'])->click();
        self::waitForShownSlides($shop, ['New in']);

        $carousel->getByRole('button', ['name' => 'Previous slide'])->click();
        self::waitForShownSlides($shop, ['Free shipping']);
    }

    /**
     * @test
     */
    public function it_initialises_a_carousel_again_in_the_builder_s_preview_once_a_slide_added_to_it_is_patched_in(): void
    {
        $entry = self::createPageWithBlocks('Campaign', 'campaign', [self::carousel([
            self::heading('0193a5be-0000-7000-8000-0000000000a2', 'Summer sale'),
            self::heading('0193a5be-0000-7000-8000-0000000000a3', 'Free shipping'),
        ])]);
        $this->signInAsAdmin();

        $page = $this->visit(sprintf('/admin/cms/entry/%s/builder', $entry->getUuid()));
        $preview = $page->frameLocator('iframe[title="Preview"]');
        Expect::locator($preview->locator('[data-cms-bridge]'))->toBeAttached();
        $carousel = $preview->locator('.cms-tree .cms-carousel');
        $dots = $carousel->locator('.cms-carousel__dot');
        Expect::locator($dots)->toHaveCount(2);
        $body = $preview->locator('body');
        $body->evaluate('() => { window.cmsTestPage = "the page before the change"; }');

        // A heading added after the first slide is a new slide
        $preview->getByRole('heading', ['name' => 'Summer sale'])->hover();
        $preview->getByRole('button', ['name' => 'Add block below'])->click();
        $page->getByRole('dialog', ['name' => 'Add block'])->getByRole('button', ['name' => 'Heading', 'exact' => true])->click();
        $page->getByLabel('Text')->fill('New in');

        Expect::locator($carousel->locator('.cms-carousel__slide'))->toHaveCount(3);
        Expect::locator($dots)->toHaveCount(3);
        Expect::locator($carousel->locator('.cms-carousel__prev'))->toHaveCount(1);
        Expect::locator($carousel->locator('.cms-carousel__next'))->toHaveCount(1);
        Expect::locator($dots->nth(0))->toHaveAttribute('aria-current', 'true');
        Expect::locator($page->getByRole('status'))->toHaveText('Saved');
        self::assertSame('the page before the change', $body->evaluate('() => window.cmsTestPage'), 'The preview was patched, not reloaded');

        // In the preview a click selects, so the editor shows a slide by selecting it in the tree: the preview scrolls
        // the track to it, and the script makes its dot current
        $page->getByRole('navigation')->getByRole('button', ['name' => 'Heading Free shipping', 'exact' => true])->click();
        Expect::locator($dots->nth(2))->toHaveAttribute('aria-current', 'true');
        Expect::locator($carousel->locator('.cms-carousel__slide')->nth(2))->toContainText('Free shipping');
    }

    /**
     * @param list<string> $texts
     */
    private static function waitForShownSlides(PageInterface $page, array $texts): void
    {
        $page->waitForFunction(sprintf('(expected) => JSON.stringify((%s)()) === JSON.stringify(expected)', self::SHOWN_SLIDES), $texts, ['timeout' => 5000]);
        self::assertSame($texts, $page->evaluate(self::SHOWN_SLIDES));
    }

    /**
     * @param list<StoredBlock> $slides
     */
    private static function carousel(array $slides): StoredBlock
    {
        return new StoredBlock(self::CAROUSEL, 'carousel', 1, children: $slides);
    }
}
