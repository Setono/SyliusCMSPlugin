<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Preview;

use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Repository\PlacementRepositoryInterface;

/**
 * The snippet the main request's preview token shows, with the placement it names, which the snippet preview's
 * endpoints and its plain page answer for. See "Snippet preview" in docs/spec/builder.md
 *
 * @internal
 */
final readonly class PreviewedSnippetProvider
{
    public function __construct(
        private PreviewContext $previewContext,
        private PreviewedEntryProvider $previewedEntry,
        private PlacementRepositoryInterface $placements,
    ) {
    }

    /**
     * The previewed snippet, or null when the main request previews none, or another entry
     *
     * @param string|null $localeCode the locale the request shows, or null for the token's
     */
    public function get(?string $localeCode = null): ?PreviewedSnippet
    {
        $preview = $this->previewContext->getPreview();
        $snippet = null === $preview ? null : $this->previewedEntry->getPreviewedContent($localeCode ?? $preview->localeCode);
        if (null === $preview || !$snippet instanceof SnippetInterface) {
            return null;
        }

        return new PreviewedSnippet(
            $preview,
            $snippet,
            null === $preview->placementUuid ? null : $this->placements->findOneOfSnippet($snippet, $preview->placementUuid),
        );
    }
}
