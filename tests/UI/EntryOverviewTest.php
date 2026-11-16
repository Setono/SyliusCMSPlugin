<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Doctrine\ORM\EntityManagerInterface;
use Playwright\Assertions\Expect;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\ChangeSet;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyManagerInterface;
use Setono\SyliusCMSPlugin\Repository\SavedChangeSetRepositoryInterface;

/**
 * The entry overview page, between an entry's list and the builder, and the ways between them.
 * See "Entry overview page" and "Into and out of the builder" in docs/spec/admin.md
 */
final class EntryOverviewTest extends UITestCase
{
    /**
     * @test
     */
    public function it_opens_the_overview_page_from_a_row_and_discards_the_unpublished_changes_there_after_asking(): void
    {
        $this->signInAsAdmin();
        $page = self::createPage('About us', 'about-us', published: true);
        self::saveDraft($page, 'About our shop', 'about-our-shop');

        $browser = $this->visit('/admin/cms/entries/page');
        $browser->locator('tbody')->getByRole('link', ['name' => 'About our shop'])->click();

        $header = $browser->locator('[data-overview-header]');
        Expect::locator($header->getByRole('heading', ['level' => 1]))->toContainText('About our shop');
        Expect::locator($header->locator('[data-status] .label'))->toHaveCount(2);
        $urls = $browser->getByRole('region', ['name' => 'URLs']);
        Expect::locator($urls)->toContainText('/en_US/about-us');
        Expect::locator($urls)->toContainText('After publishing: /en_US/about-our-shop');

        // Discarding asks first, then the overview page shows what's published
        $publishing = $browser->getByRole('region', ['name' => 'Publishing']);
        Expect::locator($publishing)->toContainText('Unpublished changes.');
        $publishing->getByRole('button', ['name' => 'Discard changes'])->click();
        $browser->locator('#confirmation-modal')->getByText('Yes')->click();

        Expect::locator($browser->locator('.sylius-flash-message'))->toContainText('The unpublished changes have been discarded.');
        Expect::locator($browser->locator('[data-overview-header]')->getByRole('heading', ['level' => 1]))->toContainText('About us');
        Expect::locator($browser->getByRole('region', ['name' => 'URLs']))->not()->toContainText('After publishing');
        self::assertNull(self::draftOf($page));
    }

    /**
     * @test
     */
    public function it_opens_the_builder_from_the_overview_page_and_leads_back_to_it_from_the_back_button_and_the_more_tab(): void
    {
        $this->signInAsAdmin();
        $page = self::createPage('About us', 'about-us', 'Hello');

        $browser = $this->visit(sprintf('/admin/cms/entry/%s', $page->getUuid()));
        $browser->getByRole('link', ['name' => 'Open in builder'])->click();
        Expect::locator($browser->getByRole('heading', ['level' => 1]))->toHaveText('About us');

        // The settings' More tab links to the overview page
        $browser->getByRole('tab', ['name' => 'More'])->click();
        $browser->getByRole('link', ['name' => 'Open the overview page'])->click();
        Expect::locator($browser->locator('[data-overview-header]'))->toContainText('About us');

        // And Back returns there too
        $browser->getByRole('link', ['name' => 'Open in builder'])->click();
        Expect::locator($browser->getByRole('heading', ['level' => 1]))->toHaveText('About us');
        $browser->getByRole('link', ['name' => 'Back'])->click();
        Expect::locator($browser->locator('[data-overview-header]'))->toContainText('About us');
        self::assertStringEndsWith(sprintf('/admin/cms/entry/%s', $page->getUuid()), $browser->url());
    }

    /**
     * Back sends the unsaved changes first. When they can't be saved, the builder stays and says why, and the editor
     * stays or leaves anyway, without them. See "Opening and leaving" in docs/spec/builder.md
     *
     * @test
     */
    public function it_stays_in_the_builder_when_the_changes_cannot_be_saved_and_leaves_without_them_when_asked_to(): void
    {
        $this->signInAsAdmin();
        $page = self::createPage('About us', 'about-us', 'Hello');
        $builderUrl = sprintf('/admin/cms/entry/%s/builder', $page->getUuid());

        $browser = $this->visit($builderUrl);
        Expect::locator($browser->getByRole('heading', ['level' => 1]))->toHaveText('About us');

        // Colleagues saved twice since the builder opened, and the server no longer keeps the first of their saves, so it
        // can't merge the builder's changes onto theirs
        self::recordColleaguesSave($page, 2);

        $browser->getByRole('button', ['name' => 'Heading Hello'])->click();
        $browser->getByLabel('Text')->fill('Hello there');
        $browser->getByRole('link', ['name' => 'Back'])->click();

        // The only dialog. playwright-php can't chain a locator onto a role name with an apostrophe
        $dialog = $browser->getByRole('dialog');
        Expect::locator($dialog)->toHaveAccessibleName("Your changes aren't saved");
        Expect::locator($dialog)->toHaveAccessibleDescription("Colleagues' changes can't be merged with yours. If you leave now, they're lost.");
        $dialog->getByRole('button', ['name' => 'Stay'])->click();

        Expect::locator($dialog)->toHaveCount(0);
        Expect::locator($browser->getByLabel('Text'))->toHaveValue('Hello there');
        self::assertStringEndsWith($builderUrl, $browser->url());

        $browser->getByRole('link', ['name' => 'Back'])->click();
        $dialog->getByRole('button', ['name' => 'Leave anyway'])->click();

        Expect::locator($browser->locator('[data-overview-header]'))->toContainText('About us');
        self::assertStringEndsWith(sprintf('/admin/cms/entry/%s', $page->getUuid()), $browser->url());
        self::assertSame(['en_US' => 'Hello'], self::reloadPage($page)->getBlockTree()->blocks[0]->fields['text'] ?? null);
    }

    /**
     * Records a colleague's save of no changes under the lock token
     */
    private static function recordColleaguesSave(PageInterface $page, int $lockToken): void
    {
        /** @var SavedChangeSetRepositoryInterface $savedChangeSets */
        $savedChangeSets = self::getContainer()->get(SavedChangeSetRepositoryInterface::class);
        $savedChangeSets->record(self::reloadPage($page), $lockToken, ChangeSet::fromArray(['changes' => []]), new \DateTimeImmutable());

        /** @var EntityManagerInterface $manager */
        $manager = self::getContainer()->get('setono_sylius_cms.manager.saved_change_set');
        $manager->flush();
    }

    /**
     * Saves a draft of the published page with this title and slug, as the builder's autosave would
     */
    private static function saveDraft(PageInterface $page, string $title, string $slug): void
    {
        /** @var WorkingCopyManagerInterface $workingCopies */
        $workingCopies = self::getContainer()->get(WorkingCopyManagerInterface::class);
        $workingCopy = $workingCopies->getWorkingCopy($page);
        $workingCopy->setTitle($title);
        $workingCopy->setSlug($slug);
        $workingCopies->save($page, $workingCopy);
    }
}
