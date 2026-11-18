<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Registry;

use Setono\SyliusCMSPlugin\Attribute\Allow;
use Setono\SyliusCMSPlugin\Attribute\Card;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\EntryTranslationInterface;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;

/**
 * A content type as the registry knows it: its #[ContentType] options and its Sylius resource
 */
final readonly class ContentTypeMetadata
{
    /** The codes of the built-in content types, which the plugin depends on and which can't be disabled */
    public const BUILT_IN = ['page', SnippetInterface::CONTENT_TYPE];

    /** What the aliases of the plugin's own resources start with, which a shipped content type's resource is one of */
    private const SHIPPED_RESOURCE_PREFIX = 'setono_sylius_cms.';

    /** The order sources list its entries in */
    public EntryOrder $order;

    /**
     * @param list<Allow>|null $blocks
     * @param class-string<EntryInterface> $modelClass
     * @param class-string|null $translationModelClass
     * @param string|null $descriptionField the property of its translations the meta description falls back to
     * @param EntryOrder|null $order the order sources list its entries in, or null for the default, newest first
     * @param list<Card> $cards how its entries look listed among others, one per display
     * @param class-string|null $categoryType the category content type of its collection
     * @param bool $disabled switched off in setono_sylius_cms.disabled.content_types: hidden from the admin, the
     *                       builder, sources and routing, while its entries keep their rows
     */
    public function __construct(
        public string $code,
        public string $label,
        public int $version,
        public ?string $prefix,
        public ?array $blocks,
        public string $modelClass,
        public ?string $translationModelClass,
        public string $resourceAlias,
        public ?string $descriptionField = null,
        ?EntryOrder $order = null,
        public array $cards = [],
        public ?string $categoryType = null,
        public bool $disabled = false,
    ) {
        $this->order = $order ?? EntryOrder::default();
    }

    public function hasOwnUrl(): bool
    {
        return null !== $this->prefix;
    }

    /**
     * Whether its entries are snippets, which show in slots and Snippet blocks rather than at a URL of their own
     */
    public function isSnippet(): bool
    {
        return is_a($this->modelClass, SnippetInterface::class, true);
    }

    /**
     * Whether it's one of the content types the plugin itself depends on, Page or Snippet, which collections never are
     */
    public function isBuiltIn(): bool
    {
        return in_array($this->code, self::BUILT_IN, true);
    }

    /**
     * Whether it ships with the plugin, as one of the plugin's own resources (setono_sylius_cms.resources), such as the
     * blog's and the FAQ's. A project's own content type is a resource of its own
     */
    public function isShipped(): bool
    {
        return str_starts_with($this->resourceAlias, self::SHIPPED_RESOURCE_PREFIX);
    }

    /**
     * A new translation of an entry of this content type, in the locale, for the entry to add
     */
    public function createTranslation(string $localeCode): EntryTranslationInterface
    {
        $class = $this->translationModelClass;
        $translation = null === $class ? null : new $class();
        if (!$translation instanceof EntryTranslationInterface) {
            throw new \LogicException(sprintf('The content type "%s" has no translation model to add the locale %s with', $this->code, $localeCode));
        }

        $translation->setLocale($localeCode);

        return $translation;
    }
}
