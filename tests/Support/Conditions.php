<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Support;

use Setono\SyliusCMSPlugin\Condition\ConditionKindInterface;
use Setono\SyliusCMSPlugin\Condition\ConditionMatcher;
use Setono\SyliusCMSPlugin\Condition\Kind\DateWindowKind;
use Setono\SyliusCMSPlugin\Field\ChoiceHandler;
use Setono\SyliusCMSPlugin\Field\ChoicesProviderInterface;
use Setono\SyliusCMSPlugin\Field\DateHandler;
use Setono\SyliusCMSPlugin\Field\DateTimeHandler;
use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Field\TextHandler;
use Setono\SyliusCMSPlugin\Field\ToggleHandler;
use Setono\SyliusCMSPlugin\Localization\FallbackChainInterface;
use Setono\SyliusCMSPlugin\Localization\FallbackValues;
use Setono\SyliusCMSPlugin\Registry\ConditionKindRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Rendering\ParamsHydrator;
use Setono\SyliusCMSPlugin\Tests\Unit\Condition\Fixture\LocaleIs;
use Setono\SyliusCMSPlugin\Tests\Unit\Condition\Fixture\SignedIn;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Translation\IdentityTranslator;

/**
 * For unit tests: decides visibility conditions as the container does, with the field types condition parameters use
 */
final class Conditions
{
    /**
     * @param array<string, ConditionKindInterface> $kinds code => kind, besides the fixture kinds locale_is and
     *                                                     signed_in and the shipped date_window
     * @param array<class-string, ChoicesProviderInterface> $choicesProviders the choices providers the kinds' parameters name
     */
    public static function matcher(array $kinds = [], array $choicesProviders = []): ConditionMatcher
    {
        $kinds += ['locale_is' => new LocaleIs(), 'signed_in' => new SignedIn(), 'date_window' => new DateWindowKind()];

        $handlers = self::fieldHandlers($choicesProviders);

        return new ConditionMatcher(
            new ConditionKindRegistry(
                array_map(static fn (ConditionKindInterface $kind): string => $kind::class, $kinds),
                new FieldReader(),
                new ServiceLocator(array_map(static fn (ConditionKindInterface $kind): \Closure => static fn (): ConditionKindInterface => $kind, $kinds)),
            ),
            new ParamsHydrator($handlers, new FallbackValues($handlers, new class() implements FallbackChainInterface {
                public function localesFor(string $localeCode): array
                {
                    return [$localeCode];
                }
            })),
        );
    }

    /**
     * @param array<class-string, ChoicesProviderInterface> $choicesProviders
     */
    public static function fieldHandlers(array $choicesProviders = []): FieldHandlerLocator
    {
        $providers = new ServiceLocator(array_map(static fn (ChoicesProviderInterface $provider): \Closure => static fn (): ChoicesProviderInterface => $provider, $choicesProviders));

        return new FieldHandlerLocator(new ServiceLocator([
            TextHandler::class => static fn (): TextHandler => new TextHandler(),
            ToggleHandler::class => static fn (): ToggleHandler => new ToggleHandler(),
            ChoiceHandler::class => static fn (): ChoiceHandler => new ChoiceHandler(new IdentityTranslator(), $providers),
            DateHandler::class => static fn (): DateHandler => new DateHandler(),
            DateTimeHandler::class => static fn (): DateTimeHandler => new DateTimeHandler(),
        ]));
    }
}
