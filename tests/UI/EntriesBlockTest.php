<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\Expect;
use Setono\SyliusCMSPlugin\Tests\Support\Blog;
use Setono\SyliusCMSPlugin\Tests\Support\Faq;

/**
 * The Entries block: an editor fills it from a collection, picks one of the displays the listed content type has cards
 * for, and shoppers see the entries through those cards. See "The Entries block" and "Cards" in
 * docs/spec/collections.md and "Entry sources" in docs/spec/sources.md
 */
final class EntriesBlockTest extends UITestCase
{
    use Blog;
    use Faq;

    /**
     * @test
     */
    public function it_lets_an_admin_user_list_the_blog_s_posts_as_grid_cards_then_as_a_carousel(): void
    {
        self::createBlogPost('Older post', 'older-post', published: true, publishedAt: new \DateTimeImmutable('2026-03-01'), excerpt: 'From the spring.');
        self::createBlogPost('Newer post', 'newer-post', published: true, publishedAt: new \DateTimeImmutable('2026-10-01'));
        self::createBlogPost('Draft post', 'draft-post');
        $this->signInAsAdmin();
        $entry = self::createPage('Blog', 'blog');

        $page = $this->visit(sprintf('/admin/cms/entry/%s/builder', $entry->getUuid()));
        $page->getByRole('button', ['name' => 'Add block', 'exact' => true])->click();
        $page->getByRole('dialog', ['name' => 'Add block'])->getByRole('button', ['name' => 'Entries', 'exact' => true])->click();

        // Filled from the blog's collection, showing as many as the field starts with
        $fields = $page->getByRole('complementary');
        $fields->getByRole('combobox', ['name' => 'Filled by'])->selectOption('entries_from_collection');
        $fields->getByRole('combobox', ['name' => 'Collection'])->selectOption('blog_post');
        Expect::locator($fields->getByRole('spinbutton', ['name' => 'Items to show']))->toHaveValue('9');
        Expect::locator($fields->getByText('2 items now'))->toBeVisible();

        // Blog posts have grid and carousel cards, so those are the displays offered
        $display = $fields->getByRole('combobox', ['name' => 'Display']);
        self::assertTexts(['Grid', 'Carousel'], $display->locator('option'));
        Expect::locator($display)->toHaveValue('grid');
        self::assertTexts(['Newer post', 'Older post'], $page->frameLocator('iframe[title="Preview"]')->locator('.cms-entries--grid .cms-blog-post-card__title'));

        Expect::locator($page->getByRole('status'))->toHaveText('Saved');
        $page->getByRole('button', ['name' => 'Publish'])->click();
        Expect::locator($page->getByText('Published', ['exact' => true]))->toBeVisible();

        $shop = $this->visit('/en_US/blog');
        self::assertTexts(['Newer post', 'Older post'], $shop->locator('.cms-tree > .cms-entries.cms-entries--grid > .cms-entries__item .cms-blog-post-card__title'));
        Expect::locator($shop->locator('.cms-blog-post-card__excerpt'))->toHaveText('From the spring.');
        $shop->getByRole('link', ['name' => 'Older post'])->click();
        Expect::locator($shop->getByRole('heading', ['level' => 1]))->toHaveText('Older post');

        // As a carousel of three per view
        $page = $this->visit(sprintf('/admin/cms/entry/%s/builder', $entry->getUuid()));
        $page->getByRole('navigation')->getByRole('button', ['name' => 'Entries', 'exact' => true])->click();
        $display = $page->getByRole('complementary')->getByRole('combobox', ['name' => 'Display']);
        self::assertTexts(['Grid', 'Carousel'], $display->locator('option'));
        $display->selectOption('carousel');
        Expect::locator($page->getByRole('status'))->toHaveText('Saved');
        $page->getByRole('button', ['name' => 'Publish'])->click();
        Expect::locator($page->getByText('Published', ['exact' => true]))->toBeVisible();

        $shop = $this->visit('/en_US/blog');
        $carousel = $shop->locator('.cms-tree > .cms-entries.cms-entries--carousel > .cms-carousel');
        Expect::locator($carousel)->toHaveAttribute('data-cms-per-view', '3');
        self::assertTexts(['Newer post', 'Older post'], $carousel->locator('.cms-carousel__slide > .cms-entries__item .cms-blog-post-card__title'));
    }

    /**
     * @test
     */
    public function it_lets_an_admin_user_list_the_faq_s_questions_as_an_accordion(): void
    {
        self::createFaqItem('How do I return an item?', 'Within 30 days.', published: true);
        self::createFaqItem('Do you ship abroad?', 'To the EU.', published: true);
        $this->signInAsAdmin();
        $entry = self::createPage('FAQ', 'faq');

        $page = $this->visit(sprintf('/admin/cms/entry/%s/builder', $entry->getUuid()));
        $page->getByRole('button', ['name' => 'Add block', 'exact' => true])->click();
        $page->getByRole('dialog', ['name' => 'Add block'])->getByRole('button', ['name' => 'Entries', 'exact' => true])->click();

        $fields = $page->getByRole('complementary');
        $fields->getByRole('combobox', ['name' => 'Filled by'])->selectOption('entries_from_collection');
        $fields->getByRole('combobox', ['name' => 'Collection'])->selectOption('faq_item');

        // FAQ items have only an accordion card. The block still shows the grid it holds until another display is picked
        $display = $fields->getByRole('combobox', ['name' => 'Display']);
        self::assertTexts(['Grid', 'Accordion'], $display->locator('option'));
        $display->selectOption('accordion');
        self::assertTexts(['How do I return an item?', 'Do you ship abroad?'], $page->frameLocator('iframe[title="Preview"]')->locator('.cms-entries--accordion details.cms-accordion-item > summary'));

        Expect::locator($page->getByRole('status'))->toHaveText('Saved');
        $page->getByRole('button', ['name' => 'Publish'])->click();
        Expect::locator($page->getByText('Published', ['exact' => true]))->toBeVisible();

        $shop = $this->visit('/en_US/faq');
        $items = $shop->locator('.cms-tree > .cms-entries.cms-entries--accordion > .cms-entries__item > details.cms-accordion-item');
        self::assertTexts(['How do I return an item?', 'Do you ship abroad?'], $items->locator('summary'));
        $items->first()->locator('summary')->click();
        Expect::locator($items->first()->locator('.cms-accordion-item__content'))->toHaveText('Within 30 days.');
    }
}
