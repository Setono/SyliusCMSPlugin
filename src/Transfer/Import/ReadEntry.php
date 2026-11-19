<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer\Import;

use Setono\SyliusCMSPlugin\Context\EntryTitle;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeMetadata;
use Setono\SyliusCMSPlugin\Transfer\Format\ExportedEntry;

/**
 * An entry of the export as the import read it, upgraded to the code's versions
 */
final readonly class ReadEntry implements ReadRecord
{
    /**
     * @param ExportedEntry $exported its file, with the entry upgraded, in the shape CanonicalJson::decode() gives
     * @param EntryInterface $content a copy of the entry no Doctrine manager manages, which holds what the export
     *                                holds: what's checked, compared and written is read from it
     * @param bool $picked whether the export picked it, rather than it coming along as a dependency
     */
    public function __construct(
        public ExportedEntry $exported,
        public EntryInterface $content,
        public ContentTypeMetadata $contentType,
        private bool $picked,
    ) {
    }

    public function kind(): RecordKind
    {
        return RecordKind::Entry;
    }

    public function uuid(): string
    {
        return $this->content->getUuid();
    }

    public function changedAt(): \DateTimeImmutable
    {
        return $this->exported->changedAt;
    }

    public function isPicked(): bool
    {
        return $this->picked;
    }

    /**
     * What names it: its title in its first translation that has one, or its UUID
     */
    public function label(): string
    {
        return EntryTitle::of($this->content);
    }

    /**
     * The entry in the entry format, upgraded, which its draft or its rows get
     *
     * @return array<array-key, mixed>
     */
    public function data(): array
    {
        return is_array($this->exported->entry) ? $this->exported->entry : [];
    }
}
