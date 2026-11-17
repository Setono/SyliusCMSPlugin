<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Translation;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Block\Heading;
use Setono\SyliusCMSPlugin\Field\ChoiceHandler;
use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Field\TextHandler;
use Setono\SyliusCMSPlugin\Localization\Availability;
use Setono\SyliusCMSPlugin\Localization\FallbackChainInterface;
use Setono\SyliusCMSPlugin\Localization\FallbackValues;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistry;
use Setono\SyliusCMSPlugin\Translation\CompleteLocales;
use Setono\SyliusCMSPlugin\Translation\Fingerprint;
use Setono\SyliusCMSPlugin\Translation\TranslatableValues;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Translation\IdentityTranslator;

/**
 * See "Completeness and outdated flags" in docs/spec/localization.md
 */
final class CompleteLocalesTest extends TestCase
{
    use ProphecyTrait;

    private const KEY = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4a';

    /**
     * @test
     */
    public function it_gives_the_locales_the_working_copy_has_a_translation_in_with_nothing_untranslated_or_outdated_by_code(): void
    {
        $page = self::page('About us', 'about-us');
        self::addTranslation($page, 'da_DK', 'Om os', 'om-os');
        // The heading is untranslated
        self::addTranslation($page, 'de_DE', 'Über uns', 'ueber-uns');
        // The heading is outdated
        self::addTranslation($page, 'nb_NO', 'Om oss', 'om-oss');
        // A translation without a title is no translation
        self::addTranslation($page, 'sv_SE', null, null);
        // A held-back translation can be complete
        self::addTranslation($page, 'fi_FI', 'Meistä', 'meista', shown: false);
        $page->setBlockTree(new StoredTree(null, [
            new StoredBlock(self::KEY, 'heading', 1, fields: ['text' => [
                'en_US' => 'Hello',
                'da_DK' => 'Hej',
                'nb_NO' => 'Hei',
                'fi_FI' => 'Hei',
            ]], translated: ['text' => [
                // Translated from what en_US had before it changed
                'nb_NO' => ['from' => 'en_US', 'fingerprint' => Fingerprint::of('Hello there')],
            ]]),
        ]));

        self::assertSame(['da_DK', 'en_US', 'fi_FI'], $this->completeLocales()->of($page));
    }

    /**
     * @test
     */
    public function it_gives_no_locales_for_an_entry_without_translations(): void
    {
        self::assertSame([], $this->completeLocales()->of(new Page()));
    }

    private function completeLocales(): CompleteLocales
    {
        $fallbackChain = $this->prophesize(FallbackChainInterface::class);
        $fallbackChain->localesFor(Argument::type('string'))->will(static fn (array $arguments): array => 'en_US' === $arguments[0] ? ['en_US'] : [$arguments[0], 'en_US']);
        $handlers = new FieldHandlerLocator(new ServiceLocator([
            TextHandler::class => static fn (): TextHandler => new TextHandler(),
            ChoiceHandler::class => static fn (): ChoiceHandler => new ChoiceHandler(new IdentityTranslator()),
        ]));

        return new CompleteLocales(
            new TranslatableValues(
                new ContentTypeRegistry(['page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page']]),
                new BlockTypeRegistry(['heading' => Heading::class], new FieldReader()),
                new LayoutRegistry([], new FieldReader()),
                new FallbackValues($handlers, $fallbackChain->reveal()),
                $fallbackChain->reveal(),
            ),
            new Availability(),
        );
    }

    private static function page(string $title, string $slug): Page
    {
        $page = new Page();
        $page->setCurrentLocale('en_US');
        $page->setFallbackLocale('en_US');
        $page->setTitle($title);
        $page->setSlug($slug);

        return $page;
    }

    private static function addTranslation(Page $page, string $localeCode, ?string $title, ?string $slug, bool $shown = true): PageTranslation
    {
        $translation = new PageTranslation();
        $translation->setLocale($localeCode);
        $translation->setTitle($title);
        $translation->setSlug($slug);
        $translation->setShown($shown);
        $page->addTranslation($translation);

        return $translation;
    }
}
