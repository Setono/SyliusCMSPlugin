<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Support;

use Setono\SyliusCMSPlugin\Binding\BindablePropertyInterface;
use Setono\SyliusCMSPlugin\Binding\BindingResolver;
use Setono\SyliusCMSPlugin\Binding\BindingValidator;
use Setono\SyliusCMSPlugin\Field\ChoiceHandler;
use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Field\TextHandler;
use Setono\SyliusCMSPlugin\Field\ToggleHandler;
use Setono\SyliusCMSPlugin\Localization\FallbackChain;
use Setono\SyliusCMSPlugin\Localization\FallbackValues;
use Setono\SyliusCMSPlugin\Registry\AttributeReader;
use Setono\SyliusCMSPlugin\Registry\BindablePropertyRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Rendering\ParamsHydrator;
use Sylius\Resource\Translation\Provider\ImmutableTranslationLocaleProvider;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Translation\IdentityTranslator;

/**
 * For unit tests: bindable properties, their registry and the binding resolver as the container wires them, with the
 * field types their parameters use
 */
final class Bindings
{
    /**
     * @param list<BindablePropertyInterface> $properties each carrying #[AsBindableProperty]
     */
    public static function registry(array $properties = []): BindablePropertyRegistry
    {
        $declarations = [];
        $services = [];
        foreach ($properties as $property) {
            $attribute = AttributeReader::bindableProperty($property::class) ?? throw new \LogicException(sprintf('%s has no #[AsBindableProperty]', $property::class));
            $binding = sprintf('%s.%s', $attribute->contextKey, $attribute->code);
            $declarations[$binding] = [
                'context_key' => $attribute->contextKey,
                'code' => $attribute->code,
                'label' => $attribute->label,
                'type' => $attribute->type->value,
                'params' => $attribute->params,
            ];
            $services[$binding] = static fn (): BindablePropertyInterface => $property;
        }

        return new BindablePropertyRegistry($declarations, new FieldReader(), new ServiceLocator($services));
    }

    /**
     * @param list<BindablePropertyInterface> $properties each carrying #[AsBindableProperty]
     */
    public static function resolver(array $properties = []): BindingResolver
    {
        return new BindingResolver(self::registry($properties), new ParamsHydrator(self::fieldHandlers(), self::fallbackValues()));
    }

    /**
     * @param list<BindablePropertyInterface> $properties each carrying #[AsBindableProperty]
     */
    public static function validator(array $properties = []): BindingValidator
    {
        return new BindingValidator(self::registry($properties), self::fieldHandlers(), self::fallbackValues(), new IdentityTranslator());
    }

    /**
     * The field types bindable properties' parameters use in the tests
     */
    public static function fieldHandlers(): FieldHandlerLocator
    {
        return new FieldHandlerLocator(new ServiceLocator([
            ToggleHandler::class => static fn (): ToggleHandler => new ToggleHandler(),
            ChoiceHandler::class => static fn (): ChoiceHandler => new ChoiceHandler(new IdentityTranslator()),
            TextHandler::class => static fn (): TextHandler => new TextHandler(),
        ]));
    }

    private static function fallbackValues(): FallbackValues
    {
        return new FallbackValues(self::fieldHandlers(), new FallbackChain(new ImmutableTranslationLocaleProvider(['en_US'], 'en_US')));
    }
}
