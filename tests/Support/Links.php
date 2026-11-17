<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Support;

use Setono\SyliusCMSPlugin\Binding\BindablePropertyInterface;
use Setono\SyliusCMSPlugin\Field\LinkHandler;
use Setono\SyliusCMSPlugin\Field\RichTextHandler;
use Setono\SyliusCMSPlugin\Link\LinkResolver;
use Setono\SyliusCMSPlugin\Link\LinkTargetInterface;
use Setono\SyliusCMSPlugin\Link\Target\UrlLinkTarget;
use Setono\SyliusCMSPlugin\Localization\FallbackChain;
use Setono\SyliusCMSPlugin\Registry\LinkTargetRegistry;
use Setono\SyliusCMSPlugin\RichText\RichTextSchema;
use Setono\SyliusCMSPlugin\Tests\Unit\Link\Fixture\RecipeLinkTarget;
use Setono\SyliusCMSPlugin\Tests\Unit\Link\Fixture\StoreLinkTarget;
use Sylius\Resource\Translation\Provider\ImmutableTranslationLocaleProvider;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * For unit tests: link targets, and the Link and rich text field types, as the container builds them, with the fixture
 * targets app_store and recipe beside the shipped url target
 */
final class Links
{
    /**
     * @param array<string, list<string>> $fallbackLocales locale code => the locales it falls back through, before en_US
     * @param list<BindablePropertyInterface> $properties the bindable properties a ref can be bound to
     */
    public static function handler(array $fallbackLocales = [], array $properties = []): LinkHandler
    {
        $registry = self::registry();

        return new LinkHandler(
            $registry,
            new LinkResolver($registry),
            new FallbackChain(new ImmutableTranslationLocaleProvider(['en_US', 'de_DE', 'da_DK'], 'en_US'), $fallbackLocales),
            Bindings::resolver($properties),
            Bindings::validator($properties),
        );
    }

    /**
     * The rich text field type, whose links resolve through the link targets
     */
    public static function richTextHandler(): RichTextHandler
    {
        return self::richTextHandlerWith([]);
    }

    /**
     * The rich text field type, whose binding tokens can show these bindable properties
     *
     * @param list<BindablePropertyInterface> $properties
     */
    public static function richTextHandlerWith(array $properties): RichTextHandler
    {
        return new RichTextHandler(new RichTextSchema(), new LinkResolver(self::registry()), Bindings::resolver($properties), Bindings::validator($properties));
    }

    /**
     * @param array<string, LinkTargetInterface> $targets code => target, in place of url, app_store and recipe
     */
    public static function registry(?array $targets = null): LinkTargetRegistry
    {
        $targets ??= ['url' => new UrlLinkTarget(), 'app_store' => new StoreLinkTarget(), 'recipe' => new RecipeLinkTarget()];

        return new LinkTargetRegistry(
            array_map(static fn (LinkTargetInterface $target): string => $target::class, $targets),
            new ServiceLocator(array_map(static fn (LinkTargetInterface $target): \Closure => static fn (): LinkTargetInterface => $target, $targets)),
        );
    }
}
