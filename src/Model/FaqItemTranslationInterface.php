<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

interface FaqItemTranslationInterface extends EntryTranslationInterface
{
    /**
     * The answer to the question its title asks, a rich text document as it's stored. Templates read it hydrated, with
     * cms_field(entry, 'answer') or cms_value(entry, 'answer')
     *
     * @return array<array-key, mixed>|null
     */
    public function getAnswer(): ?array;

    /**
     * @param array<array-key, mixed>|null $answer a ProseMirror document
     */
    public function setAnswer(?array $answer): void;
}
