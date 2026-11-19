<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin;

use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Preset\EditorPresetSaverInterface;
use Setono\SyliusCMSPlugin\Preset\Preset;
use Setono\SyliusCMSPlugin\Preset\StartingPoint;
use Setono\SyliusCMSPlugin\Tests\Functional\Fixture\StartingPointsKernel;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredLayout;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Creating an entry from a starting point in the create dialog: Blank, or one of the content type's starting points,
 * from code and from editors, the default preselected. The new entry gets the starting point's blocks, copied as
 * inserting copies them, and its layout with the layout's field values, or the default layout when that one is gone.
 * Its fields start empty and its settings as for any new entry. With StartingPointsKernel, whose code presets are
 * Page's default Campaign page, on the landing layout, and Countdown page, with a block of a removed type. See
 * "Starting points" in docs/spec/layouts-and-presets.md and "Create dialog" in docs/spec/admin.md
 */
final class StartingPointsTest extends WebTestCase
{
    use Entries;

    private const BLOCK = '0193a5be-0000-7000-8000-00000000e001';

    private const NEW_KEY_PATTERN = '/^(?!0193a5be-0000-7000-8000-00000000)[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';

    protected static function getKernelClass(): string
    {
        return StartingPointsKernel::class;
    }

    /**
     * @test
     */
    public function it_offers_blank_and_the_content_types_starting_points_from_code_and_from_editors_with_the_default_preselected(): void
    {
        $client = self::signedInClient();
        $launch = self::saveStartingPoint('Our launch page', 'page', new StoredLayout('standard', 1), 'The launch, as we did it');
        self::saveStartingPoint('Footer', SnippetInterface::CONTENT_TYPE, null);
        self::editorPresetSaver()->save(new Preset('A block preset', [self::heading(self::BLOCK, 'Winter sale')]));

        $crawler = $client->request('GET', '/admin/cms/entries/page/new');

        self::assertResponseIsSuccessful();
        $fieldset = $crawler->filter('fieldset[data-cms-create-entry-starting-points]');
        self::assertSame('Starting point', $fieldset->filter('legend')->text());
        $cards = $fieldset->filter('.cms-starting-point-card');
        self::assertSame(['Blank', 'Campaign page', 'Countdown page', 'Our launch page'], $cards->each(static fn (Crawler $card): string => $card->filter('.cms-layout-card__label')->text()));
        self::assertSame(['', 'campaign_page', 'countdown_page', $launch], $cards->each(static fn (Crawler $card): string => (string) $card->filter('input')->attr('value')));
        // A code preset shows its thumbnail, and the others a plain card
        self::assertSame('/build/app/campaign-page.svg', $cards->eq(1)->filter('img')->attr('src'));
        self::assertCount(0, $cards->eq(3)->filter('img'));
        self::assertSame('The launch, as we did it', $cards->eq(3)->filter('.cms-starting-point-card__description')->text());
        // The code preset marked as the default is picked to start with
        self::assertSame(['campaign_page'], $crawler->filter('input[name="setono_sylius_cms_create_entry[startingPoint]"]:checked')->each(static fn (Crawler $radio): string => (string) $radio->attr('value')));
        // One with blocks of a type the code no longer has can't be picked, and says why
        self::assertNotNull($cards->eq(2)->filter('input')->attr('disabled'));
        self::assertStringContainsString('countdown', $cards->eq(2)->filter('.cms-starting-point-card__issues')->text());
        self::assertNull($cards->eq(1)->filter('input')->attr('disabled'));
    }

    /**
     * @test
     */
    public function it_creates_a_page_from_a_starting_point_with_its_blocks_and_its_layout_with_the_layouts_values(): void
    {
        $client = self::signedInClient();
        self::addLocales('WEB', 'da_DK');

        $client->request('GET', '/admin/cms/entries/page/new');
        $client->submitForm('Create', [
            'setono_sylius_cms_create_entry[title]' => 'Summer sale',
            'setono_sylius_cms_create_entry[slug]' => 'summer-sale',
            'setono_sylius_cms_create_entry[startingPoint]' => 'campaign_page',
        ]);

        $page = self::onlyPage();
        self::assertResponseRedirects(sprintf('/admin/cms/entry/%s/builder', $page->getUuid()));
        $tree = $page->getBlockTree();
        self::assertSame(['heading', 'rich_text'], array_map(static fn (StoredBlock $block): string => $block->type, $tree->blocks));
        foreach ($tree->blocks as $block) {
            self::assertMatchesRegularExpression(self::NEW_KEY_PATTERN, $block->key);
        }

        // The values of the locales of the page's channels are copied, and translation keys resolve in them
        self::assertSame(['level' => 'h1', 'text' => ['da_DK' => 'Sommerudsalg', 'en_US' => 'Summer sale']], $tree->blocks[0]->fields);
        self::assertEquals(new StoredLayout('landing', 1, ['tagline' => ['en_US' => 'Winter sale', 'da_DK' => 'Vinterudsalg'], 'transparentHeader' => true]), $tree->layout);

        // Its fields start empty, but for the title and slug the dialog asks for, and its settings as for any new entry
        self::assertSame('Summer sale', $page->getTranslation('en_US')->getTitle());
        self::assertSame('summer-sale', $page->getTranslation('en_US')->getSlug());
        self::assertNull($page->getTranslation('en_US')->getMetaTitle());
        self::assertNull($page->getTranslation('en_US')->getMetaDescription());
        self::assertFalse($page->isPublished());
        self::assertTrue($page->isEnabled());
        self::assertSame(['WEB'], $page->getChannelCodes());
    }

    /**
     * @test
     */
    public function it_uses_the_starting_points_layout_rather_than_the_one_picked_for_blank(): void
    {
        $client = self::signedInClient();

        $client->request('GET', '/admin/cms/entries/page/new');
        $client->submitForm('Create', [
            'setono_sylius_cms_create_entry[title]' => 'Summer sale',
            'setono_sylius_cms_create_entry[slug]' => 'summer-sale',
            'setono_sylius_cms_create_entry[layout]' => 'standard',
            'setono_sylius_cms_create_entry[startingPoint]' => 'campaign_page',
        ]);

        self::assertSame('landing', self::onlyPage()->getBlockTree()->layout?->code);
    }

    /**
     * @test
     */
    public function it_creates_a_blank_page_in_the_layout_the_editor_picked_when_blank_is_picked(): void
    {
        $client = self::signedInClient();

        $client->request('GET', '/admin/cms/entries/page/new');
        $client->submitForm('Create', [
            'setono_sylius_cms_create_entry[title]' => 'About us',
            'setono_sylius_cms_create_entry[slug]' => 'about-us',
            'setono_sylius_cms_create_entry[layout]' => 'landing',
            'setono_sylius_cms_create_entry[startingPoint]' => '',
        ]);

        $tree = self::onlyPage()->getBlockTree();
        self::assertSame([], $tree->blocks);
        self::assertEquals(new StoredLayout('landing', 1), $tree->layout);
    }

    /**
     * @test
     */
    public function it_creates_an_entry_from_an_editors_starting_point_in_the_default_layout_without_the_values_when_its_layout_is_gone(): void
    {
        $client = self::signedInClient();
        $retired = self::saveStartingPoint('Retired layout page', 'page', new StoredLayout('retired', 1, ['wide' => true]), blocks: [self::heading(self::BLOCK, 'Welcome')]);

        $client->request('GET', '/admin/cms/entries/page/new');
        $client->submitForm('Create', [
            'setono_sylius_cms_create_entry[title]' => 'About us',
            'setono_sylius_cms_create_entry[slug]' => 'about-us',
            'setono_sylius_cms_create_entry[startingPoint]' => $retired,
        ]);

        $tree = self::onlyPage()->getBlockTree();
        self::assertEquals(new StoredLayout('standard', 1), $tree->layout);
        self::assertSame(['heading'], array_map(static fn (StoredBlock $block): string => $block->type, $tree->blocks));
        self::assertMatchesRegularExpression(self::NEW_KEY_PATTERN, $tree->blocks[0]->key);
    }

    /**
     * @test
     */
    public function it_creates_an_entry_of_any_content_type_with_a_block_tree_from_a_starting_point_preselecting_blank_without_a_default(): void
    {
        $client = self::signedInClient();
        $footer = self::saveStartingPoint('Footer', SnippetInterface::CONTENT_TYPE, null, blocks: [self::heading(self::BLOCK, 'Need help?')]);

        $crawler = $client->request('GET', '/admin/cms/entries/snippet/new');
        self::assertSame([''], $crawler->filter('input[name="setono_sylius_cms_create_entry[startingPoint]"]:checked')->each(static fn (Crawler $radio): string => (string) $radio->attr('value')));

        $client->submitForm('Create', [
            'setono_sylius_cms_create_entry[title]' => 'Footer',
            'setono_sylius_cms_create_entry[startingPoint]' => $footer,
        ]);

        /** @var RepositoryInterface<SnippetInterface> $repository */
        $repository = self::getContainer()->get('setono_sylius_cms.repository.snippet');
        $snippet = $repository->findOneBy([]);
        self::assertInstanceOf(SnippetInterface::class, $snippet);
        self::assertNull($snippet->getBlockTree()->layout);
        self::assertSame(['heading'], array_map(static fn (StoredBlock $block): string => $block->type, $snippet->getBlockTree()->blocks));
    }

    /**
     * @test
     */
    public function it_offers_no_starting_point_for_a_content_type_without_any_or_without_a_block_tree(): void
    {
        $client = self::signedInClient();
        self::saveStartingPoint('Flagship store', 'store_location', null);

        $client->request('GET', '/admin/cms/entries/announcement/new');
        self::assertSelectorNotExists('[name="setono_sylius_cms_create_entry[startingPoint]"]');

        $client->request('GET', '/admin/cms/entries/store_location/new');
        self::assertSelectorNotExists('[name="setono_sylius_cms_create_entry[startingPoint]"]');
    }

    /**
     * @test
     *
     * @testWith ["countdown_page"]
     *           ["unknown"]
     */
    public function it_refuses_a_starting_point_with_blocks_of_removed_types_or_one_it_does_not_offer(string $startingPoint): void
    {
        $client = self::signedInClient();

        $crawler = $client->request('GET', '/admin/cms/entries/page/new');
        $form = $crawler->selectButton('Create')->form();
        $values = $form->getPhpValues();
        self::assertIsArray($values['setono_sylius_cms_create_entry']);
        $values['setono_sylius_cms_create_entry'] = ['title' => 'Countdown', 'slug' => 'countdown', 'startingPoint' => $startingPoint] + $values['setono_sylius_cms_create_entry'];
        $crawler = $client->request('POST', $form->getUri(), $values);

        self::assertResponseStatusCodeSame(422);
        self::assertCount(1, $crawler->filter('fieldset[data-cms-create-entry-starting-points] .sylius-validation-error'));
        /** @var RepositoryInterface<PageInterface> $repository */
        $repository = self::getContainer()->get('setono_sylius_cms.repository.page');
        self::assertSame([], $repository->findAll());
    }

    /**
     * @param list<StoredBlock> $blocks
     *
     * @return string the starting point's UUID
     */
    private static function saveStartingPoint(string $name, string $contentType, ?StoredLayout $layout, ?string $description = null, array $blocks = []): string
    {
        return (string) self::editorPresetSaver()->save(new Preset($name, $blocks, description: $description, startingPoint: new StartingPoint($contentType, $layout)))->uuid;
    }

    private static function editorPresetSaver(): EditorPresetSaverInterface
    {
        /** @var EditorPresetSaverInterface $saver */
        $saver = self::getContainer()->get(EditorPresetSaverInterface::class);

        return $saver;
    }

    private static function signedInClient(): KernelBrowser
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');

        return $client;
    }

    private static function onlyPage(): PageInterface
    {
        /** @var RepositoryInterface<PageInterface> $repository */
        $repository = self::getContainer()->get('setono_sylius_cms.repository.page');
        $pages = $repository->findAll();
        self::assertCount(1, $pages);
        $page = reset($pages);
        self::assertInstanceOf(PageInterface::class, $page);

        return $page;
    }
}
