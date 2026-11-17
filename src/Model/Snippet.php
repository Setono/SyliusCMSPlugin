<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Setono\SyliusCMSPlugin\Attribute\Allow;
use Setono\SyliusCMSPlugin\Attribute\ContentType;

/**
 * The built-in content type for named, reusable block trees without their own URL, shown in slots through placements,
 * which aren't part of it. Its translations are EntryTranslation itself. See "Built-in content types" in
 * docs/spec/content-model.md
 */
#[ContentType(
    code: 'snippet',
    label: 'setono_sylius_cms.content_type.snippet',
    blocks: [new Allow('*')],
)]
class Snippet extends Entry implements SnippetInterface
{
    /**
     * The host-page context it needs, as context keys. The column is nullable, as every content type's own column is
     *
     * @var list<string>|null
     */
    protected ?array $context = [];

    public function getContext(): array
    {
        return $this->context ?? [];
    }

    public function setContext(array $context): void
    {
        $this->context = array_values(array_unique($context));
    }

    protected function createTranslation(): EntryTranslationInterface
    {
        return new EntryTranslation();
    }
}
