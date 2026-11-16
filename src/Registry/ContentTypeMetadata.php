<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Registry;

use Setono\SyliusCMSPlugin\Attribute\Allow;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\EntryTranslationInterface;

/**
 * A content type as the registry knows it: its #[ContentType] options and its Sylius resource
 */
final readonly class ContentTypeMetadata
{
    /**
     * @param list<Allow>|null $blocks
     * @param class-string<EntryInterface> $modelClass
     * @param class-string|null $translationModelClass
     * @param string|null $descriptionField the property of its translations the meta description falls back to
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
    ) {
    }

    public function hasOwnUrl(): bool
    {
        return null !== $this->prefix;
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
