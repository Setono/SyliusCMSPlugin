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
use Setono\SyliusCMSPlugin\Query\CompletenessQueryInterface;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistry;
use Setono\SyliusCMSPlugin\Translation\CompleteLocales;
use Setono\SyliusCMSPlugin\Translation\CompletenessCounter;
use Setono\SyliusCMSPlugin\Translation\TranslatableValues;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Translation\IdentityTranslator;

final class CompletenessCounterTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_stores_the_locales_each_working_copy_is_complete_in_by_the_entrys_id(): void
    {
        $translated = self::page();
        self::addTranslation($translated, 'da_DK', 'Om os', 'om-os');
        $untranslated = self::page();
        // Its slug is untranslated
        self::addTranslation($untranslated, 'da_DK', 'Om os', null);
        $query = $this->prophesize(CompletenessQueryInterface::class);

        $this->counter($query->reveal())->count([7 => $translated, 9 => $untranslated]);

        $query->store([7 => ['da_DK', 'en_US'], 9 => ['en_US']])->shouldHaveBeenCalledOnce();
    }

    private function counter(CompletenessQueryInterface $query): CompletenessCounter
    {
        $fallbackChain = $this->prophesize(FallbackChainInterface::class);
        $fallbackChain->localesFor(Argument::type('string'))->willReturn(['en_US']);
        $handlers = new FieldHandlerLocator(new ServiceLocator([
            TextHandler::class => static fn (): TextHandler => new TextHandler(),
            ChoiceHandler::class => static fn (): ChoiceHandler => new ChoiceHandler(new IdentityTranslator()),
        ]));

        return new CompletenessCounter(new CompleteLocales(
            new TranslatableValues(
                new ContentTypeRegistry(['page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page']]),
                new BlockTypeRegistry(['heading' => Heading::class], new FieldReader()),
                new LayoutRegistry([], new FieldReader()),
                new FallbackValues($handlers, $fallbackChain->reveal()),
                $fallbackChain->reveal(),
            ),
            new Availability(),
        ), $query);
    }

    private static function page(): Page
    {
        $page = new Page();
        $page->setCurrentLocale('en_US');
        $page->setFallbackLocale('en_US');
        $page->setTitle('About us');
        $page->setSlug('about-us');

        return $page;
    }

    private static function addTranslation(Page $page, string $localeCode, string $title, ?string $slug): void
    {
        $translation = new PageTranslation();
        $translation->setLocale($localeCode);
        $translation->setTitle($title);
        $translation->setSlug($slug);
        $page->addTranslation($translation);
    }
}
