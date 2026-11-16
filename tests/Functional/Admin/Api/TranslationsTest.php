<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api;

use Setono\SyliusCMSPlugin\Model\PageInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Translating an entry one locale at a time in the builder: adding a translation, its values and its shown switch.
 * See "Which locales an entry has" in docs/spec/localization.md and "Held-back translations" in docs/spec/publishing.md
 */
final class TranslationsTest extends BuilderApiTestCase
{
    private const KEY = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f';

    /**
     * @test
     */
    public function a_translation_added_before_the_first_publish_starts_shown(): void
    {
        $client = self::createClient();
        self::addLocales('WEB', 'da_DK');
        $page = self::createPage('About us', 'about-us', 'Hello');
        $csrfToken = self::openBuilder($client, $page);

        self::save($client, $page, [self::setTitle('da_DK', 'Om os')], $csrfToken);

        self::assertResponseIsSuccessful();
        $danish = self::reloadPage($page)->getTranslations()->get('da_DK');
        self::assertNotNull($danish);
        self::assertSame('Om os', $danish->getTitle());
        self::assertTrue($danish->isShown());
    }

    /**
     * @test
     */
    public function a_translation_added_after_the_first_publish_starts_held_back(): void
    {
        $client = self::createClient();
        self::addLocales('WEB', 'da_DK');
        $page = self::createPage('About us', 'about-us', 'Hello', published: true);
        $csrfToken = self::openBuilder($client, $page);

        self::save($client, $page, [self::setTitle('da_DK', 'Om os'), ['op' => 'set_entry_value', 'field' => 'slug', 'locale' => 'da_DK', 'value' => 'om-os']], $csrfToken);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('"da_DK":{"shown":false,"fields":{"metaDescription":null,"metaTitle":null,"slug":"om-os","title":"Om os"},"translated":{}}', (string) self::draftOf($page)?->getContent());
        self::assertSame(['en_US'], self::reloadPage($page)->getTranslations()->getKeys(), 'The rows hold the published content');

        $response = self::json($client, 'GET', '/admin/cms/api/entries/' . $page->getUuid());
        self::assertIsArray($response);
        self::assertIsArray($response['entry'] ?? null);
        self::assertIsArray($response['entry']['translations'] ?? null);
        self::assertSame(['shown' => false, 'fields' => ['metaDescription' => null, 'metaTitle' => null, 'slug' => 'om-os', 'title' => 'Om os'], 'translated' => []], $response['entry']['translations']['da_DK'] ?? null);
    }

    /**
     * The shown switch is drafted, so a translation goes live together with its finished text
     *
     * @test
     */
    public function a_held_back_translation_shows_once_it_is_switched_on_and_published(): void
    {
        $client = self::createClient();
        self::addLocales('WEB', 'da_DK');
        $page = self::createPage('About us', 'about-us', 'Hello', published: true);
        $csrfToken = self::openBuilder($client, $page);

        $client->request('GET', '/da_DK/about-us');
        self::assertResponseStatusCodeSame(404, 'The page has no translation in Danish yet');

        self::save($client, $page, [
            self::setTitle('da_DK', 'Om os'),
            ['op' => 'set_entry_value', 'field' => 'slug', 'locale' => 'da_DK', 'value' => 'about-us'],
            ['op' => 'set_value', 'key' => self::KEY, 'field' => 'text', 'locale' => 'da_DK', 'value' => 'Hej'],
        ], $csrfToken);
        self::publish($client, $page, $csrfToken);

        $client->request('GET', '/da_DK/about-us');
        self::assertResponseStatusCodeSame(404, 'The Danish translation is held back');
        self::assertRouteSame('setono_sylius_cms_shop_entry');

        self::save($client, $page, [['op' => 'set_shown', 'locale' => 'da_DK', 'shown' => true]], $csrfToken);

        $client->request('GET', '/da_DK/about-us');
        self::assertResponseStatusCodeSame(404, 'Switching it on is drafted, so it waits for the publish');

        self::publish($client, $page, $csrfToken);

        $client->request('GET', '/da_DK/about-us');
        self::assertResponseStatusCodeSame(200);
        self::assertSelectorTextSame('.cms-heading', 'Hej');

        $client->request('GET', '/en_US/about-us');
        self::assertResponseStatusCodeSame(200);
        self::assertSelectorTextSame('.cms-heading', 'Hello');
    }

    /**
     * @test
     */
    public function it_answers_a_clash_over_a_title_a_colleague_changed_in_the_same_locale(): void
    {
        $client = self::createClient();
        self::addLocales('WEB', 'da_DK');
        $page = self::createPage('About us', 'about-us', 'Hello');
        $csrfToken = self::openBuilder($client, $page);

        self::save($client, $page, [self::setTitle('en_US', 'About them')], $csrfToken, 0);
        $response = self::save($client, $page, [self::setTitle('en_US', 'About me'), self::setTitle('da_DK', 'Om mig')], $csrfToken, 0);

        self::assertIsArray($response);
        self::assertSame([['kind' => 'entry_value', 'field' => 'title', 'locale' => 'en_US', 'mine' => 'About me', 'theirs' => 'About them']], $response['clashes'] ?? null);
        self::assertSame('About them', self::reloadPage($page)->getTranslation('en_US')->getTitle());
        self::assertSame('Om mig', self::reloadPage($page)->getTranslation('da_DK')->getTitle());
    }

    /**
     * @test
     */
    public function it_refuses_a_value_of_an_entry_field_the_content_type_does_not_have(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello');
        $csrfToken = self::openBuilder($client, $page);

        $response = self::save($client, $page, [['op' => 'set_entry_value', 'field' => 'subtitle', 'locale' => 'en_US', 'value' => 'Hi']], $csrfToken);

        self::assertResponseStatusCodeSame(400);
        self::assertIsArray($response);
        self::assertSame('The content type "page" has no entry field "subtitle"', $response['error'] ?? null);
    }

    /**
     * Undoing "Add translation" empties the translation's values, which leaves a translation without a title in the
     * draft. That's no translation (localization.md), so publish doesn't validate it: not its title, nor its slug
     *
     * @test
     */
    public function it_publishes_when_adding_a_translation_was_undone(): void
    {
        $client = self::createClient();
        self::addLocales('WEB', 'da_DK');
        $page = self::createPage('About us', 'about-us', 'Hello', published: true);
        $csrfToken = self::openBuilder($client, $page);
        self::save($client, $page, [self::setTitle('da_DK', 'About us'), ['op' => 'set_entry_value', 'field' => 'slug', 'locale' => 'da_DK', 'value' => 'about-us']], $csrfToken);

        // The undo, as the builder sends it
        self::save($client, $page, [
            ['op' => 'set_entry_value', 'field' => 'slug', 'locale' => 'da_DK', 'value' => null],
            ['op' => 'set_entry_value', 'field' => 'title', 'locale' => 'da_DK', 'value' => null],
        ], $csrfToken);
        self::publish($client, $page, $csrfToken);

        $client->request('GET', '/en_US/about-us');
        self::assertResponseIsSuccessful();
        $client->request('GET', '/da_DK/about-us');
        self::assertResponseStatusCodeSame(404);
    }

    /**
     * An untitled translation of another entry claims no URL, so it doesn't clash
     *
     * @test
     */
    public function another_entrys_translation_without_a_title_claims_no_url(): void
    {
        $client = self::createClient();
        self::addLocales('WEB', 'da_DK');
        $other = self::createPage('Contact', 'contact-us', published: true);
        self::addTranslation($other, 'da_DK', null, 'om-os');
        $page = self::createPage('About us', 'about-us', 'Hello', published: true);
        $csrfToken = self::openBuilder($client, $page);

        self::save($client, $page, [self::setTitle('da_DK', 'Om os'), ['op' => 'set_entry_value', 'field' => 'slug', 'locale' => 'da_DK', 'value' => 'om-os']], $csrfToken);
        self::publish($client, $page, $csrfToken);
    }

    /**
     * @return array<string, string>
     */
    private static function setTitle(string $locale, string $title): array
    {
        return ['op' => 'set_entry_value', 'field' => 'title', 'locale' => $locale, 'value' => $title];
    }

    /**
     * @param list<array<string, mixed>> $changes
     */
    private static function save(KernelBrowser $client, PageInterface $page, array $changes, string $csrfToken, ?int $lockToken = null): mixed
    {
        return self::json($client, 'PATCH', '/admin/cms/api/entries/' . $page->getUuid(), ['lockToken' => $lockToken ?? self::lockToken($client, $page), 'changes' => $changes], $csrfToken);
    }

    private static function publish(KernelBrowser $client, PageInterface $page, string $csrfToken): void
    {
        self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/publish', $page->getUuid()), null, $csrfToken);
        self::assertResponseIsSuccessful();
    }
}
