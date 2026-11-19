<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin;

use Setono\SyliusCMSPlugin\Preset\EditorPresetSaverInterface;
use Setono\SyliusCMSPlugin\Preset\Preset;
use Setono\SyliusCMSPlugin\Preset\PresetProviderInterface;
use Setono\SyliusCMSPlugin\Preset\StartingPoint;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredLayout;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\DomCrawler\Crawler;

/**
 * The Presets screen in the Content hub's library: starting points and block presets in two tabs, code presets
 * read-only and marked as coming from code, and editor presets renamed, recategorised, deleted and downloaded. A preset
 * with blocks of removed types is flagged. See "Presets" in docs/spec/admin.md
 */
final class PresetsScreenTest extends WebTestCase
{
    use ClockSensitiveTrait;
    use Entries;

    private const KEY = '0193a5be-0000-7000-8000-000000000001';

    /**
     * @test
     */
    public function it_lists_the_block_presets_with_code_presets_read_only_and_editor_presets_editable(): void
    {
        $client = self::signedIn();
        $promo = self::savePreset(new Preset('Promo', [self::heading(self::KEY, 'Winter sale')], 'Campaigns', 'For the winter sale'));
        self::savePreset(self::landingPage());

        $crawler = $client->request('GET', '/admin/cms/presets?tab=block_presets');

        self::assertResponseIsSuccessful();
        self::assertSame(['Starting points', 'Block presets'], $crawler->filter('[data-preset-tabs] a.item')->each(static fn (Crawler $tab): string => trim($tab->text())));
        self::assertSame('Block presets', trim($crawler->filter('[data-preset-tabs] a.item.active')->text()));
        self::assertSame(['column_pair', 'hero', 'product_spotlight', $promo->id()], self::presetIds($crawler));

        $hero = $crawler->filter('[data-preset="hero"]');
        self::assertSame(['Hero', 'Banners', 'A heading and an introduction'], [trim($hero->filter('[data-name]')->text()), trim($hero->filter('[data-category]')->text()), trim($hero->filter('[data-description]')->text())]);
        self::assertSame('From code', trim($hero->filter('[data-from-code]')->text()));
        self::assertCount(0, $hero->filter('form, [data-cms-dialog-open]'));

        $row = $crawler->filter(sprintf('[data-preset="%s"]', $promo->id()));
        self::assertSame(['Promo', 'Campaigns', 'For the winter sale'], [trim($row->filter('[data-name]')->text()), trim($row->filter('[data-category]')->text()), trim($row->filter('[data-description]')->text())]);
        self::assertCount(0, $row->filter('[data-from-code]'));
        self::assertSame(sprintf('/admin/cms/api/presets/%s/export', $promo->id()), $row->filter('a[download]')->attr('href'));
        self::assertCount(1, $row->filter(sprintf('form[action="/admin/cms/presets/%s/delete"]', $promo->id())));
        // The edit dialog suggests the categories in use, those of code presets too
        $form = $crawler->filter(sprintf('form[action="/admin/cms/presets/%s"]', $promo->id()));
        self::assertSame(['Promo', 'Campaigns', 'For the winter sale'], [$form->filter('input[name="name"]')->attr('value'), $form->filter('input[name="category"]')->attr('value'), trim($form->filter('textarea[name="description"]')->text())]);
        self::assertSame(['Banners', 'Campaigns', 'Layout', 'Pages', 'Products'], $crawler->filter(sprintf('datalist#%s option', (string) $form->filter('input[name="category"]')->attr('list')))->each(static fn (Crawler $option): string => (string) $option->attr('value')));
    }

    /**
     * @test
     */
    public function it_lists_the_starting_points_in_the_first_tab_with_their_content_types(): void
    {
        $client = self::signedIn();
        $landing = self::savePreset(self::landingPage());

        $crawler = $client->request('GET', '/admin/cms/presets');

        self::assertResponseIsSuccessful();
        self::assertSame('Starting points', trim($crawler->filter('[data-preset-tabs] a.item.active')->text()));
        self::assertSame([$landing->id()], self::presetIds($crawler));
        self::assertSame('Page', trim($crawler->filter('[data-preset] [data-content-type]')->text()));
    }

    /**
     * @test
     */
    public function it_renames_and_recategorises_an_editor_preset_at_once(): void
    {
        self::mockTime('2026-11-20 10:00:00');
        $client = self::signedIn();
        $promo = self::savePreset(new Preset('Promo', [self::heading(self::KEY, 'Winter sale')], 'Campaigns'));
        $crawler = $client->request('GET', '/admin/cms/presets?tab=block_presets');

        self::mockTime('2026-11-21 09:00:00');
        $form = $crawler->filter(sprintf('form[action="/admin/cms/presets/%s"]', $promo->id()))->form(['name' => 'Spring promo', 'category' => 'Seasonal', 'description' => '']);
        $client->submit($form);

        self::assertResponseRedirects('/admin/cms/presets?tab=block_presets');
        $crawler = $client->followRedirect();
        self::assertSelectorTextContains('.sylius-flash-message.positive', 'The preset has been saved.');
        self::assertSame('Spring promo', trim($crawler->filter(sprintf('[data-preset="%s"] [data-name]', $promo->id()))->text()));

        $saved = self::preset($promo->id());
        self::assertSame(['Spring promo', 'Seasonal', null], [$saved->name, $saved->category, $saved->description]);
        self::assertEquals(new \DateTimeImmutable('2026-11-21T09:00:00Z'), $saved->changedAt);
        self::assertCount(1, $saved->blocks);
    }

    /**
     * @test
     */
    public function it_refuses_an_empty_name_and_keeps_the_preset_as_it_is(): void
    {
        $client = self::signedIn();
        $promo = self::savePreset(new Preset('Promo', [], 'Campaigns'));
        $crawler = $client->request('GET', '/admin/cms/presets?tab=block_presets');

        $client->submit($crawler->filter(sprintf('form[action="/admin/cms/presets/%s"]', $promo->id()))->form(['name' => ' ']));

        $client->followRedirect();
        self::assertSelectorTextContains('.sylius-flash-message.negative', 'A preset needs a name.');
        self::assertSame('Promo', self::preset($promo->id())->name);
    }

    /**
     * @test
     */
    public function it_deletes_an_editor_preset(): void
    {
        $client = self::signedIn();
        $promo = self::savePreset(new Preset('Promo', [], 'Campaigns'));
        $crawler = $client->request('GET', '/admin/cms/presets?tab=block_presets');

        $client->submit($crawler->filter(sprintf('form[action="/admin/cms/presets/%s/delete"]', $promo->id()))->form());

        self::assertResponseRedirects('/admin/cms/presets?tab=block_presets');
        $crawler = $client->followRedirect();
        self::assertSelectorTextContains('.sylius-flash-message.positive', 'The preset has been deleted.');
        self::assertSame(['column_pair', 'hero', 'product_spotlight'], self::presetIds($crawler));
    }

    /**
     * @test
     */
    public function it_refuses_a_change_without_the_page_s_csrf_token_and_to_a_preset_that_is_not_an_editor_preset(): void
    {
        $client = self::signedIn();
        $promo = self::savePreset(new Preset('Promo', [], 'Campaigns'));

        $client->request('POST', sprintf('/admin/cms/presets/%s', $promo->id()), ['name' => 'Spring promo', '_csrf_token' => 'wrong']);
        self::assertResponseStatusCodeSame(403);

        $client->request('POST', '/admin/cms/presets/0193a5be-0000-7000-8000-00000000ffff/delete', ['_csrf_token' => 'wrong']);
        self::assertResponseStatusCodeSame(404);

        self::assertSame('Promo', self::preset($promo->id())->name);
    }

    /**
     * @test
     */
    public function it_flags_a_preset_with_blocks_of_a_type_the_code_no_longer_has(): void
    {
        $client = self::signedIn();
        $old = self::savePreset(new Preset('Old promo', [new StoredBlock(self::KEY, 'countdown', 1)]));

        $crawler = $client->request('GET', '/admin/cms/presets?tab=block_presets');

        self::assertSame(
            ['It has blocks of the type "countdown", which the code no longer has, so inserting it leaves them out.'],
            $crawler->filter(sprintf('[data-preset="%s"] [data-issues] li', $old->id()))->each(static fn (Crawler $issue): string => trim($issue->text())),
        );
    }

    /**
     * @test
     */
    public function it_needs_an_admin_user(): void
    {
        $client = self::createClient();

        $client->request('GET', '/admin/cms/presets');

        self::assertResponseRedirects('http://localhost/admin/login');
    }

    private static function signedIn(): KernelBrowser
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');

        return $client;
    }

    private static function savePreset(Preset $preset): Preset
    {
        /** @var EditorPresetSaverInterface $saver */
        $saver = self::getContainer()->get(EditorPresetSaverInterface::class);

        return $saver->save($preset);
    }

    private static function preset(string $uuid): Preset
    {
        self::entityManager()->clear();

        /** @var PresetProviderInterface $presets */
        $presets = self::getContainer()->get(PresetProviderInterface::class);

        return $presets->find($uuid) ?? throw new \LogicException(sprintf('There is no preset %s', $uuid));
    }

    private static function landingPage(): Preset
    {
        return new Preset('Landing page', [self::heading(self::KEY, 'Welcome')], 'Pages', startingPoint: new StartingPoint('page', new StoredLayout('standard', 1, ['showTitle' => false])));
    }

    /**
     * @return array<mixed>
     */
    private static function presetIds(Crawler $crawler): array
    {
        return $crawler->filter('[data-preset]')->each(static fn (Crawler $row): string => (string) $row->attr('data-preset'));
    }
}
