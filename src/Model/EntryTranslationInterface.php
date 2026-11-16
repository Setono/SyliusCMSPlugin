<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Sylius\Resource\Model\ResourceInterface;
use Sylius\Resource\Model\TranslationInterface;

/**
 * An entry's content in one locale
 */
interface EntryTranslationInterface extends ResourceInterface, TranslationInterface
{
    public function getId(): ?int;

    public function getTitle(): ?string;

    public function setTitle(?string $title): void;

    /**
     * Set only for content types with their own URL
     */
    public function getSlug(): ?string;

    public function setSlug(?string $slug): void;

    /**
     * The translation's "Show in <language>" switch, which holds it back when it's off: the entry then doesn't show in
     * its locale. See "Held-back translations" in docs/spec/publishing.md
     */
    public function isShown(): bool;

    public function setShown(bool $shown): void;

    /**
     * The translation state of its translatable fields: for each value translated from another locale, that locale and
     * the fingerprint of the value it was translated from. A value typed without a source has none.
     * See "Translation state" in docs/spec/content-model.md
     *
     * @return array<string, array{from: string, fingerprint: string}> field name => where its value was translated from
     */
    public function getTranslated(): array;

    /**
     * @param array<string, array{from: string, fingerprint: string}> $translated field name => where its value was translated from
     */
    public function setTranslated(array $translated): void;
}
