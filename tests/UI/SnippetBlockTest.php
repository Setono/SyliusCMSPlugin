<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\Expect;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Repository\DraftRepositoryInterface;

/**
 * A page shows a footer snippet through a Snippet block, which the builder renders in place and marks as shared, and
 * then detaches it to edit a copy of its blocks there, which leaves the snippet as it is. See "Snippet references" in
 * docs/spec/builder.md and "Snippet" in docs/spec/blocks.md
 */
final class SnippetBlockTest extends UITestCase
{
    private const HEADING = '0193a5be-0000-7000-8000-000000000001';

    /**
     * @test
     */
    public function it_lets_an_admin_user_show_a_footer_snippet_in_a_page_see_it_marked_as_shared_then_detach_it_and_edit_the_copy_alone(): void
    {
        $footer = self::createSnippet('Footer', [self::heading(self::HEADING, 'Free shipping over $50')], published: true);
        $page = self::createPage('About us', 'about-us');
        $this->signInAsAdmin();

        $browser = $this->visit(sprintf('/admin/cms/entry/%s/builder', $page->getUuid()));
        $browser->getByRole('button', ['name' => 'Add block', 'exact' => true])->click();
        $browser->getByRole('dialog', ['name' => 'Add block'])->getByRole('button', ['name' => 'Snippet', 'exact' => true])->click();

        // Its picker offers the footer, which the preview then shows in place, and the tree marks as shared
        $selection = $browser->getByRole('complementary');
        $selection->getByRole('button', ['name' => 'Snippet', 'exact' => true])->click();
        $browser->getByRole('option', ['name' => 'Footer'])->click();

        $tree = $browser->getByRole('navigation', ['name' => 'Blocks']);
        Expect::locator($tree->getByRole('button', ['name' => 'Snippet Footer']))->toBeVisible();
        Expect::locator($tree->getByText('Shared', ['exact' => true]))->toBeVisible();
        Expect::locator($selection->getByRole('button', ['name' => 'Edit snippet']))->toBeVisible();
        Expect::locator($browser->frameLocator('iframe')->locator('.cms-tree > h2.cms-heading'))->toHaveText('Free shipping over $50');
        Expect::locator($browser->getByRole('status'))->toHaveText('Saved');

        // Detached, the page holds a copy of the footer's heading, which is edited here alone
        $selection->getByRole('button', ['name' => 'Detach to edit here'])->click();
        Expect::locator($tree->getByRole('button', ['name' => 'Heading Free shipping over $50']))->toHaveAttribute('aria-pressed', 'true');
        Expect::locator($tree->getByText('Shared', ['exact' => true]))->toHaveCount(0);
        $browser->getByRole('textbox', ['name' => 'Text'])->fill('Free shipping over $40 on this page');

        Expect::locator($browser->getByRole('status'))->toHaveText('Saved');
        Expect::locator($browser->frameLocator('iframe')->locator('.cms-tree > h2.cms-heading'))->toHaveText('Free shipping over $40 on this page');

        // The footer is as it was: the same heading, and no draft
        $shown = self::entityManager()->find($footer::class, $footer->getId());
        self::assertInstanceOf(SnippetInterface::class, $shown);
        self::entityManager()->refresh($shown);
        $heading = $shown->getBlockTree()->findBlock(self::HEADING);
        self::assertNotNull($heading);
        self::assertSame(['en_US' => 'Free shipping over $50'], $heading->fields['text'] ?? null);
        /** @var DraftRepositoryInterface $drafts */
        $drafts = self::getContainer()->get('setono_sylius_cms.repository.draft');
        self::assertNull($drafts->findOneByEntry($shown));
    }
}
