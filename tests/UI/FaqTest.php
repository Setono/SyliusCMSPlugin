<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\Expect;
use Playwright\Page\PageInterface;

/**
 * The FAQ, from the hub's FAQ item: questions created with their answers in the builder, which has no tree pane for
 * them, and dragged into order in their list. See "Shipped content types" and "In the admin" in
 * docs/spec/collections.md
 */
final class FaqTest extends UITestCase
{
    /**
     * @test
     */
    public function an_admin_user_creates_three_faq_items_with_answers_in_the_builder_and_drags_them_into_a_new_order(): void
    {
        $this->signInAsAdmin();

        // The FAQ item lists the questions and the categories, switched by All, Questions and Categories
        $browser = $this->visit('/admin/cms');
        $browser->locator('[data-hub-menu]')->getByRole('link', ['name' => 'FAQ'])->click();
        Expect::locator($browser->getByRole('heading', ['level' => 1]))->toContainText('FAQ');
        $browser->getByRole('navigation', ['name' => 'FAQ'])->getByRole('link', ['name' => 'Questions'])->click();
        Expect::locator($browser->getByRole('navigation', ['name' => 'FAQ'])->getByRole('link', ['name' => 'Questions']))->toHaveAttribute('aria-current', 'page');

        $browser->getByRole('link', ['name' => 'Create'])->click();
        $dialog = $browser->getByRole('dialog', ['name' => 'New FAQ item']);
        // Its title is the question, and it has no URL, so no slug
        Expect::locator($dialog->getByLabel('Slug'))->toHaveCount(0);
        $dialog->getByLabel('Title')->fill('How fast do you ship?');
        $dialog->getByRole('button', ['name' => 'Create'])->click();
        $this->answer($browser, 'How fast do you ship?', 'Within three days.');

        foreach (['Can I return an item?' => 'Within 30 days.', 'Do you ship abroad?' => 'Yes, to all of Europe.'] as $question => $answer) {
            $browser = $this->visit('/admin/cms/entries/faq_item/new');
            $browser->getByLabel('Title')->fill($question);
            $browser->getByRole('button', ['name' => 'Create'])->click();
            $this->answer($browser, $question, $answer);
        }

        // New questions go last, and dragging the last one to the top saves the new order at once
        $browser = $this->visit('/admin/cms/entries/faq_item');
        $titles = $browser->locator('[data-column="title"] strong');
        self::assertTexts(['How fast do you ship?', 'Can I return an item?', 'Do you ship abroad?'], $titles);

        $rows = $browser->locator('tbody tr');
        $rows->nth(2)->dragTo($rows->nth(0), ['targetPosition' => ['x' => 20, 'y' => 5]]);

        Expect::locator($browser->getByRole('status'))->toHaveText('The order is saved.');
        self::assertTexts(['Do you ship abroad?', 'How fast do you ship?', 'Can I return an item?'], $titles);

        $browser = $this->visit('/admin/cms/entries/faq_item');
        self::assertTexts(['Do you ship abroad?', 'How fast do you ship?', 'Can I return an item?'], $browser->locator('[data-column="title"] strong'));
    }

    /**
     * In the builder the new item opened in: it has no tree pane and no page to preview, so its answer is typed in its
     * settings, and the builder saves it
     */
    private function answer(PageInterface $browser, string $question, string $answer): void
    {
        Expect::locator($browser->getByRole('heading', ['level' => 1]))->toHaveText($question);
        Expect::locator($browser->locator('#cms-builder')->getByRole('navigation'))->toHaveCount(0);
        Expect::locator($browser->getByRole('button', ['name' => 'Add block', 'exact' => true]))->toHaveCount(0);
        Expect::locator($browser->getByText('This entry has no page of its own to preview.', ['exact' => false]))->toBeVisible();

        $field = $browser->getByRole('textbox', ['name' => 'Answer']);
        $field->click();
        $field->pressSequentially($answer);

        Expect::locator($browser->getByRole('status'))->toHaveText('Saved');
        Expect::locator($field)->toHaveText($answer);
    }
}
