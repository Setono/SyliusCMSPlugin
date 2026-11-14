<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Registry;

use Setono\SyliusCMSPlugin\Attribute\Allow;
use Setono\SyliusCMSPlugin\Model\EntryInterface;

/**
 * A content type as the registry knows it: its #[ContentType] options and its Sylius resource
 */
final readonly class ContentTypeMetadata
{
    /**
     * @param list<Allow>|null $blocks
     * @param class-string<EntryInterface> $modelClass
     * @param class-string|null $translationModelClass
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
    ) {
    }

    public function hasOwnUrl(): bool
    {
        return null !== $this->prefix;
    }
}
