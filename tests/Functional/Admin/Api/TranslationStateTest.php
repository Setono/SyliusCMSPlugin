<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api;

use Doctrine\Persistence\ObjectManager;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Translation\Fingerprint;
use Setono\SyliusCMSPlugin\Translation\TranslatableValue;
use Setono\SyliusCMSPlugin\Translation\TranslationStateInterface;
use Setono\SyliusCMSPlugin\Translation\ValueState;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Which values are untranslated or outdated in each locale, what the builder loads of it, and saving where a value was
 * translated from. See "Completeness and outdated flags" in docs/spec/localization.md
 */
final class TranslationStateTest extends BuilderApiTestCase
{
    private const HEADING = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4a';

    private const OTHER = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4b';

    /**
     * @test
     */
    public function entry_show_answers_the_untranslated_and_outdated_counts_of_each_locale_the_entry_has(): void
    {
        $client = self::createClient();
        $page = self::translatedPage('Hello there', 'Hello');
        self::openBuilder($client, $page);

        $response = self::json($client, 'GET', '/admin/cms/api/entries/' . $page->getUuid());

        self::assertIsArray($response);
        self::assertSame(['da_DK' => ['untranslated' => 1, 'outdated' => 1], 'en_US' => ['untranslated' => 0, 'outdated' => 0]], $response['completeness'] ?? null);
        self::assertSame(
            ['text' => ['da_DK' => ['from' => 'en_US', 'fingerprint' => Fingerprint::of('Hello')]]],
            self::block($response, 0)['translated'] ?? null,
        );
    }

    /**
     * @test
     */
    public function a_save_marks_an_outdated_value_up_to_date(): void
    {
        $client = self::createClient();
        $page = self::translatedPage('Hello there', 'Hello');
        $csrfToken = self::openBuilder($client, $page);

        self::save($client, $page, [[
            'op' => 'set_translated',
            'key' => self::HEADING,
            'field' => 'text',
            'locale' => 'da_DK',
            'translated' => ['from' => 'en_US', 'fingerprint' => Fingerprint::of('Hello there')],
        ]], $csrfToken);

        self::assertResponseIsSuccessful();
        self::assertSame(['text' => ['da_DK' => ['from' => 'en_US', 'fingerprint' => Fingerprint::of('Hello there')]]], self::reloadPage($page)->getBlockTree()->blocks[0]->translated);
        $response = self::json($client, 'GET', '/admin/cms/api/entries/' . $page->getUuid());
        self::assertIsArray($response);
        self::assertSame(['da_DK' => ['untranslated' => 1, 'outdated' => 0], 'en_US' => ['untranslated' => 0, 'outdated' => 0]], $response['completeness'] ?? null);
    }

    /**
     * A translation's own fields record where they were translated from in the draft, which publishing copies onto the rows
     *
     * @test
     */
    public function where_a_title_was_translated_from_is_drafted_and_published(): void
    {
        $client = self::createClient();
        $page = self::translatedPage('Hello', 'Hello', published: true);
        $csrfToken = self::openBuilder($client, $page);
        $source = ['from' => 'en_US', 'fingerprint' => Fingerprint::of('About us')];

        self::save($client, $page, [
            ['op' => 'set_entry_value', 'field' => 'title', 'locale' => 'da_DK', 'value' => 'Om os'],
            ['op' => 'set_entry_translated', 'field' => 'title', 'locale' => 'da_DK', 'translated' => $source],
        ], $csrfToken);

        self::assertResponseIsSuccessful();
        self::assertSame([], self::reloadPage($page)->getTranslation('da_DK')->getTranslated(), 'The rows hold the published content');
        self::assertStringContainsString(sprintf('"translated":{"title":{"from":"en_US","fingerprint":"%s"}}', $source['fingerprint']), (string) self::draftOf($page)?->getContent());

        self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/publish', $page->getUuid()), null, $csrfToken);

        self::assertResponseIsSuccessful();
        self::assertSame(['title' => $source], self::reloadPage($page)->getTranslation('da_DK')->getTranslated());
    }

    /**
     * @test
     */
    public function it_refuses_translation_state_for_a_shared_field(): void
    {
        $client = self::createClient();
        $page = self::translatedPage('Hello', 'Hello');
        $csrfToken = self::openBuilder($client, $page);

        $response = self::save($client, $page, [['op' => 'set_translated', 'key' => self::HEADING, 'field' => 'level', 'locale' => 'da_DK', 'translated' => null]], $csrfToken);

        self::assertResponseStatusCodeSame(400);
        self::assertIsArray($response);
        self::assertSame('The field "level" is shared by all translations, so it has no translation state', $response['error'] ?? null);
    }

    /**
     * A translator writes into the draft, as an editor's save does, so an editor with the entry open pulls it in
     *
     * @test
     */
    public function a_translator_reads_the_values_of_the_draft_and_writes_a_translation_into_it(): void
    {
        $client = self::createClient();
        $page = self::translatedPage('Hello', 'Hello', published: true);
        self::openBuilder($client, $page);
        $lockToken = self::lockToken($client, $page);
        $state = self::getContainer()->get(TranslationStateInterface::class);
        self::assertInstanceOf(TranslationStateInterface::class, $state);

        $values = array_values(array_filter([...$state->values(self::reloadPage($page), 'da_DK')], static fn (TranslatableValue $value): bool => ValueState::Untranslated === $value->state));
        self::assertCount(1, $values);
        self::assertSame('block.' . self::OTHER . '.text', $values[0]->path);
        self::assertSame('en_US', $values[0]->sourceLocaleCode);
        self::assertSame('Welcome', $values[0]->sourceValue);

        $state->write(self::reloadPage($page), 'da_DK', $values[0]->path, 'Velkommen', 'en_US', (string) $values[0]->sourceFingerprint);

        self::assertNull(self::reloadPage($page)->getBlockTree()->blocks[1]->fields['text']['da_DK'] ?? null, 'The rows hold the published content');
        self::assertSame($lockToken + 1, self::lockToken($client, $page));
        $written = array_values(array_filter([...$state->values(self::reloadPage($page), 'da_DK')], static fn (TranslatableValue $value): bool => $value->path === $values[0]->path));
        self::assertCount(1, $written);
        self::assertSame(ValueState::UpToDate, $written[0]->state);
        self::assertSame('Velkommen', $written[0]->value);
    }

    /**
     * A page in English and Danish: a heading whose Danish text was translated from $translatedFrom, and another only in English
     */
    private static function translatedPage(string $english, string $translatedFrom, bool $published = false): PageInterface
    {
        $heading = self::heading(self::HEADING, $english);
        $heading->setValue('text', 'da_DK', 'Hej');
        $heading->setTranslated('text', 'da_DK', ['from' => 'en_US', 'fingerprint' => Fingerprint::of($translatedFrom)]);
        $other = new StoredBlock(self::OTHER, 'heading', 1);
        $other->setValue('text', 'en_US', 'Welcome');

        $page = self::createPageWithBlocks('About us', 'about-us', [$heading, $other], $published);
        $danish = new PageTranslation();
        $danish->setLocale('da_DK');
        $danish->setTitle('Om os');
        $danish->setSlug('om-os');
        $page->addTranslation($danish);

        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('setono_sylius_cms.manager.page');
        $manager->flush();

        return $page;
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function block(mixed $response, int $index): array
    {
        self::assertIsArray($response);
        self::assertIsArray($response['entry'] ?? null);
        self::assertIsArray($response['entry']['tree'] ?? null);
        self::assertIsArray($response['entry']['tree']['blocks'] ?? null);
        self::assertIsArray($response['entry']['tree']['blocks'][$index] ?? null);

        return $response['entry']['tree']['blocks'][$index];
    }

    /**
     * @param list<array<string, mixed>> $changes
     */
    private static function save(KernelBrowser $client, PageInterface $page, array $changes, string $csrfToken): mixed
    {
        return self::json($client, 'PATCH', '/admin/cms/api/entries/' . $page->getUuid(), ['lockToken' => self::lockToken($client, $page), 'changes' => $changes], $csrfToken);
    }
}
