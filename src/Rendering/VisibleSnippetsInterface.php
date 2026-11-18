<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Rendering;

use Setono\SyliusCMSPlugin\Model\SnippetInterface;

/**
 * The snippets a slot may show: those that are visible, with the published content their rows hold. A placement of a
 * snippet that isn't visible shows nothing (ADR 0005). See "When an entry shows" in docs/spec/content-model.md
 */
interface VisibleSnippetsInterface
{
    /**
     * Of the snippets with these UUIDs, those visible in the render context, by UUID, loaded with one query
     *
     * @param list<string> $uuids
     *
     * @return array<string, SnippetInterface>
     */
    public function get(array $uuids, RenderContext $context): array;
}
