<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Collection;

use Setono\SyliusCMSPlugin\Context\EntryContext;
use Setono\SyliusCMSPlugin\Field\Codes;
use Setono\SyliusCMSPlugin\Field\HydrationContext;
use Setono\SyliusCMSPlugin\Field\Reference;
use Setono\SyliusCMSPlugin\Field\ReferenceType;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\SourceMetadata;
use Setono\SyliusCMSPlugin\Registry\SourceRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\UnknownContentTypeException;
use Setono\SyliusCMSPlugin\Rendering\ParamsHydrator;
use Setono\SyliusCMSPlugin\Source\ListedContentTypeAwareInterface;
use Setono\SyliusCMSPlugin\Source\StoredSource;

/**
 * Which content types the entries of an Entries list are of, so the Entries block offers their card displays: the one
 * a source lists, which it tells with its parameters and the host page's entry through ListedContentTypeAwareInterface,
 * or else the one its #[AsSource(of:)] names, and when the entries are hand-picked, the content type of each picked
 * entry the shopper may see. See "The Entries block" in docs/spec/collections.md and "Sources" in
 * docs/spec/extending.md
 *
 * @internal
 */
final readonly class ListedContentTypes
{
    public function __construct(
        private SourceRegistryInterface $sources,
        private ParamsHydrator $paramsHydrator,
        private ContentTypeRegistryInterface $contentTypes,
    ) {
    }

    /**
     * The references to load before asking of(): the picked entries, or the source's parameters' references
     *
     * @return list<Reference>
     */
    public function references(mixed $value): array
    {
        if (Codes::isCodeList($value)) {
            return Codes::references(ReferenceType::ENTRY, $value);
        }

        $stored = StoredSource::fromValue($value);
        if (null === $stored || !$this->sources->has($stored->code)) {
            return [];
        }

        return $this->paramsHydrator->references($this->sources->get($stored->code)->fields, $stored->params);
    }

    /**
     * The codes of the content types the list's entries are of, each once, or null when it can't tell: nothing is
     * picked, or none of it shows, or the source doesn't say, or lists entries of any content type
     *
     * @param mixed $value the Entries field's stored value
     * @param HydrationContext $context the host page, and the references references() named, loaded
     *
     * @return list<string>|null
     */
    public function of(mixed $value, HydrationContext $context): ?array
    {
        if (Codes::isCodeList($value)) {
            return $this->ofPicked($value, $context);
        }

        $stored = StoredSource::fromValue($value);
        if (null === $stored || !$this->sources->has($stored->code)) {
            return null;
        }

        $listed = $this->ofSource($this->sources->get($stored->code), $stored->params, $context);

        return null === $listed ? null : [$listed];
    }

    /**
     * @param list<non-empty-string> $uuids
     *
     * @return list<string>|null
     */
    private function ofPicked(array $uuids, HydrationContext $context): ?array
    {
        $codes = [];
        foreach ($uuids as $uuid) {
            $entry = $context->references->get(ReferenceType::ENTRY, $uuid);
            $code = $entry instanceof EntryInterface ? $this->codeOf($entry) : null;
            if (null !== $code) {
                $codes[$code] = true;
            }
        }

        return [] === $codes ? null : array_keys($codes);
    }

    /**
     * @param array<array-key, mixed> $params
     */
    private function ofSource(SourceMetadata $source, array $params, HydrationContext $context): ?string
    {
        if ($source->source instanceof ListedContentTypeAwareInterface) {
            $host = $context->renderContext->get(EntryContext::ENTRY);
            $listed = $source->source->getListedContentType(
                $this->paramsHydrator->hydrate($source->params, $source->fields, $params, $context),
                $host instanceof EntryInterface ? $this->codeOf($host) : null,
            );
            if (null !== $listed) {
                return $listed;
            }
        }

        // What it lists: one content type's class, or entries of any content type
        try {
            return EntryInterface::class === $source->of ? null : $this->contentTypes->getByClass($source->of)->code;
        } catch (UnknownContentTypeException) {
            return null;
        }
    }

    private function codeOf(EntryInterface $entry): ?string
    {
        try {
            return $this->contentTypes->getByEntry($entry)->code;
        } catch (UnknownContentTypeException) {
            return null;
        }
    }
}
