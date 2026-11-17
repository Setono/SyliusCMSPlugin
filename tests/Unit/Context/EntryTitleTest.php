<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Context;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Context\EntryTitle;
use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Localization\FallbackChain;
use Setono\SyliusCMSPlugin\Localization\FallbackValues;
use Setono\SyliusCMSPlugin\Model\EntryTranslation;
use Setono\SyliusCMSPlugin\Model\Snippet;
use Sylius\Resource\Translation\Provider\ImmutableTranslationLocaleProvider;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class EntryTitleTest extends TestCase
{
    /**
     * Its locale isn't asked for, as Sylius's getTranslation() would add a translation in a locale it doesn't have
     *
     * @test
     */
    public function it_names_an_entry_by_its_first_title_or_else_by_its_uuid(): void
    {
        self::assertSame('Fodnote', EntryTitle::of(self::snippet(['da_DK' => 'Fodnote', 'en_US' => 'Footer'])));
        self::assertSame('Footer', EntryTitle::of(self::snippet(['da_DK' => '', 'en_US' => 'Footer'])));

        $untitled = self::snippet([]);
        self::assertSame($untitled->getUuid(), EntryTitle::of($untitled));
    }

    /**
     * Such as a picker names it in the admin user's locale
     *
     * @test
     */
    public function it_names_an_entry_in_a_locale_along_its_fallback_chain_or_else_by_its_first_title(): void
    {
        $values = new FallbackValues(new FieldHandlerLocator(new ServiceLocator([])), new FallbackChain(new ImmutableTranslationLocaleProvider(['en_US', 'da_DK', 'sv_SE'], 'en_US')));
        $snippet = self::snippet(['sv_SE' => 'Sidfot', 'en_US' => 'Footer']);

        self::assertSame('Footer', EntryTitle::inLocale($snippet, 'da_DK', $values), 'Danish falls back to English');
        self::assertSame('Sidfot', EntryTitle::inLocale($snippet, 'sv_SE', $values));
        self::assertSame('Sidfot', EntryTitle::inLocale(self::snippet(['sv_SE' => 'Sidfot']), 'da_DK', $values), 'None along the chain');
    }

    /**
     * @param array<string, string> $titles by locale
     */
    private static function snippet(array $titles): Snippet
    {
        $snippet = new Snippet();
        foreach ($titles as $locale => $title) {
            $translation = new EntryTranslation();
            $translation->setLocale($locale);
            $translation->setTitle($title);
            $snippet->addTranslation($translation);
        }

        return $snippet;
    }
}
