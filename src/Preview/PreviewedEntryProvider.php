<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Preview;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyProviderInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Rendering\EntryUrlMatch;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Routing\EntryPath;

/**
 * The entry the builder's preview edits. It renders its working copy at the working copy's URL, ignoring its own
 * publish state, while everything else on the page shows what shoppers see. See "Preview mode" in docs/spec/builder.md
 */
final readonly class PreviewedEntryProvider
{
    public function __construct(
        private PreviewContext $previewContext,
        private EntryRepositoryInterface $entryRepository,
        private WorkingCopyProviderInterface $workingCopyProvider,
        private ContentTypeRegistryInterface $contentTypes,
    ) {
    }

    /**
     * The working copy of the entry the main request previews, when it previews one in this locale
     */
    public function getWorkingCopy(string $localeCode): ?EntryInterface
    {
        $preview = $this->previewContext->getPreview();
        if (null === $preview || $preview->localeCode !== $localeCode) {
            return null;
        }

        $entry = $this->entryRepository->findOneByUuid($preview->entryUuid);

        return null === $entry ? null : $this->workingCopyProvider->getWorkingCopy($entry);
    }

    /**
     * Matches the previewed entry at its working copy's URL, which can differ from its published one
     */
    public function match(string $localeCode, string $path): ?EntryUrlMatch
    {
        $entry = $this->getWorkingCopy($localeCode);
        if (null === $entry) {
            return null;
        }

        $contentType = $this->contentTypes->getByEntry($entry);

        return EntryPath::of($contentType, $entry, $localeCode) === $path ? new EntryUrlMatch($entry->getUuid(), $contentType->code) : null;
    }
}
