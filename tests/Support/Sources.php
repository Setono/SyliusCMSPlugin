<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Support;

use Setono\SyliusCMSPlugin\Field\ChoiceHandler;
use Setono\SyliusCMSPlugin\Field\ChoicesProviderInterface;
use Setono\SyliusCMSPlugin\Field\EntriesHandler;
use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Field\ListFieldHandler;
use Setono\SyliusCMSPlugin\Field\ProductHandler;
use Setono\SyliusCMSPlugin\Field\TaxonHandler;
use Setono\SyliusCMSPlugin\Field\ToggleHandler;
use Setono\SyliusCMSPlugin\Localization\FallbackChain;
use Setono\SyliusCMSPlugin\Localization\FallbackValues;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Registry\SourceRegistry;
use Setono\SyliusCMSPlugin\Rendering\ParamsHydrator;
use Setono\SyliusCMSPlugin\Source\SourceInterface;
use Setono\SyliusCMSPlugin\Source\SourceResolver;
use Sylius\Resource\Translation\Provider\ImmutableTranslationLocaleProvider;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Translation\IdentityTranslator;

/**
 * For unit tests: the sources, their registry and the list field handler as the container wires them, with the field
 * types sources' parameters use
 */
final class Sources
{
    /**
     * @param array<string, SourceInterface> $sources code => source
     * @param list<string> $disabled
     */
    public static function registry(array $sources, array $disabled = []): SourceRegistry
    {
        return new SourceRegistry(
            array_map(static fn (SourceInterface $source): string => $source::class, $sources),
            new FieldReader(),
            new ServiceLocator(array_map(static fn (SourceInterface $source): \Closure => static fn (): SourceInterface => $source, $sources)),
            $disabled,
        );
    }

    /**
     * @param array<string, SourceInterface> $sources code => source
     * @param list<string> $disabled
     */
    public static function resolver(array $sources, array $disabled = []): SourceResolver
    {
        $registry = self::registry($sources, $disabled);
        $handlers = self::fieldHandlers($registry);

        return self::resolverOf($registry, $handlers);
    }

    /**
     * @param array<string, SourceInterface> $sources code => source
     * @param list<string> $disabled
     */
    public static function listFieldHandler(array $sources = [], array $disabled = []): ListFieldHandler
    {
        $registry = self::registry($sources, $disabled);

        return new ListFieldHandler($registry, self::resolverOf($registry, self::fieldHandlers($registry)));
    }

    /**
     * Hydrates sources' parameters, with these choices providers, and Entries fields of these content types
     *
     * @param array<class-string, ChoicesProviderInterface> $choicesProviders by class
     */
    public static function paramsHydrator(array $choicesProviders = [], ?ContentTypeRegistryInterface $contentTypes = null): ParamsHydrator
    {
        $handlers = self::fieldHandlers(self::registry([]), $choicesProviders, $contentTypes);

        return new ParamsHydrator($handlers, new FallbackValues($handlers, new FallbackChain(new ImmutableTranslationLocaleProvider(['en_US'], 'en_US'))));
    }

    private static function resolverOf(SourceRegistry $registry, FieldHandlerLocator $handlers): SourceResolver
    {
        $fallbackValues = new FallbackValues($handlers, new FallbackChain(new ImmutableTranslationLocaleProvider(['en_US'], 'en_US')));

        return new SourceResolver($registry, new ParamsHydrator($handlers, $fallbackValues), $handlers, $fallbackValues, new IdentityTranslator());
    }

    /**
     * @param array<class-string, ChoicesProviderInterface> $choicesProviders by class
     */
    private static function fieldHandlers(SourceRegistry $registry, array $choicesProviders = [], ?ContentTypeRegistryInterface $contentTypes = null): FieldHandlerLocator
    {
        $handlers = null;
        $handlers = new FieldHandlerLocator(new ServiceLocator([
            ToggleHandler::class => static fn (): ToggleHandler => new ToggleHandler(),
            ChoiceHandler::class => static fn (): ChoiceHandler => new ChoiceHandler(new IdentityTranslator(), new ServiceLocator(array_map(static fn (ChoicesProviderInterface $provider): \Closure => static fn (): ChoicesProviderInterface => $provider, $choicesProviders))),
            EntriesHandler::class => static function () use ($registry, &$handlers, $contentTypes): EntriesHandler {
                \assert($handlers instanceof FieldHandlerLocator);

                return new EntriesHandler(new ListFieldHandler($registry, self::resolverOf($registry, $handlers)), $contentTypes ?? new ContentTypeRegistry([]));
            },
            ProductHandler::class => static fn (): ProductHandler => new ProductHandler(),
            TaxonHandler::class => static fn (): TaxonHandler => new TaxonHandler(),
            ListFieldHandler::class => static function () use ($registry, &$handlers): ListFieldHandler {
                \assert($handlers instanceof FieldHandlerLocator);

                return new ListFieldHandler($registry, self::resolverOf($registry, $handlers));
            },
        ]));

        return $handlers;
    }
}
