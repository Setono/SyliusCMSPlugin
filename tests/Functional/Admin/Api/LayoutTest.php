<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api;

use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Translation\Fingerprint;
use Setono\SyliusCMSPlugin\Translation\TranslationStateInterface;
use Setono\SyliusCMSPlugin\Translation\ValueState;
use Setono\SyliusCMSPlugin\Tree\StoredLayout;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * The layout choice and its fields' values are drafted with the tree. See "Picking and switching" in
 * docs/spec/layouts-and-presets.md
 */
final class LayoutTest extends BuilderApiTestCase
{
    /**
     * @test
     */
    public function a_layout_switch_in_the_draft_does_not_change_the_shop_until_it_is_published(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello', published: true);
        $csrfToken = self::openBuilder($client, $page);

        self::save($client, $page, $csrfToken, [
            ['op' => 'set_layout', 'layout' => ['code' => 'landing', 'version' => 1, 'fields' => ['tagline' => ['en_US' => 'Big news']], 'translated' => []]],
            ['op' => 'set_layout_value', 'layout' => 'landing', 'field' => 'transparentHeader', 'locale' => null, 'value' => true],
        ]);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('"layout":{"code":"landing","version":1,"fields":{"tagline":{"en_US":"Big news"},"transparentHeader":true},"translated":{}}', (string) self::draftOf($page)?->getContent());

        $client->request('GET', '/en_US/about-us');
        self::assertSelectorTextSame('h1', 'About us');
        self::assertSelectorNotExists('.app-landing');

        self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/publish', $page->getUuid()), null, $csrfToken);
        self::assertResponseIsSuccessful();

        $client->request('GET', '/en_US/about-us');
        self::assertSelectorNotExists('h1');
        self::assertSelectorTextSame('.app-landing.app-landing--transparent .app-landing__tagline', 'Big news');
        self::assertSame('landing', self::reloadPage($page)->getBlockTree()->layout?->code);
    }

    /**
     * @test
     */
    public function it_refuses_a_value_of_a_field_the_layout_does_not_have(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello');
        $csrfToken = self::openBuilder($client, $page);

        $response = self::save($client, $page, $csrfToken, [['op' => 'set_layout_value', 'layout' => 'standard', 'field' => 'tagline', 'locale' => 'en_US', 'value' => 'Big news']]);

        self::assertResponseStatusCodeSame(400);
        self::assertSame(['error' => 'The layout "standard" has no field "tagline"'], $response);
    }

    /**
     * @test
     */
    public function a_translator_writes_a_layout_value_into_the_draft(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello');
        self::addLocales('WEB', 'da_DK');
        $page->setBlockTree(new StoredTree(new StoredLayout('landing', 1, ['tagline' => ['en_US' => 'Big news']]), $page->getBlockTree()->blocks));
        self::getContainer()->get('setono_sylius_cms.manager.page')->flush();

        /** @var TranslationStateInterface $translationState */
        $translationState = self::getContainer()->get(TranslationStateInterface::class);
        $translationState->write(self::reloadPage($page), 'da_DK', 'layout.tagline', 'Nyhed', 'en_US', Fingerprint::of('Big news'));

        $tree = self::reloadPage($page)->getBlockTree();
        self::assertSame(['tagline' => ['da_DK' => 'Nyhed', 'en_US' => 'Big news']], $tree->layout?->fields);
        foreach ($translationState->values(self::reloadPage($page), 'da_DK') as $value) {
            if ('layout.tagline' === $value->path) {
                self::assertSame(ValueState::UpToDate, $value->state);

                return;
            }
        }

        self::fail('The layout\'s tagline is no translatable value');
    }

    /**
     * @param list<array<string, mixed>> $changes
     */
    private static function save(KernelBrowser $client, PageInterface $page, string $csrfToken, array $changes): mixed
    {
        return self::json($client, 'PATCH', '/admin/cms/api/entries/' . $page->getUuid(), ['lockToken' => self::lockToken($client, $page), 'changes' => $changes], $csrfToken);
    }
}
