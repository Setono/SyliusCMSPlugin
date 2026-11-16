<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Translation;

use Setono\SyliusCMSPlugin\Builder\ChangeSet\InvalidChangeSetException;
use Setono\SyliusCMSPlugin\Model\EntryInterface;

/**
 * The per-value translation state of an entry's draft, which a future translator, AI or not, reads and fills. The
 * completeness counts and outdated flags read the same state. See "Translation state" in docs/spec/extending.md and
 * "A future translator" in docs/spec/localization.md
 */
interface TranslationStateInterface
{
    /**
     * Every translatable value of the entry's draft for $localeCode, with its state, source locale, source value and source fingerprint.
     * The draft is the entry's working copy: its draft when it has one, and otherwise its rows
     *
     * @return iterable<TranslatableValue>
     */
    public function values(EntryInterface $entry, string $localeCode): iterable;

    /**
     * Writes a translated value into the draft, recording its source locale and $sourceFingerprint: the fingerprint of the source value that was actually translated.
     * A source that changed while the translation was in flight leaves the value outdated. It's saved as an editor's
     * change is, so editors with the entry open pull it in, and a value an editor set in the same field and locale in
     * the meantime wins
     *
     * @param string $path entry.<field>, layout.<field> or block.<key>.<field>, as values() gives it
     *
     * @throws \InvalidArgumentException when the path isn't one of the entry's values, or $sourceFingerprint isn't a fingerprint
     * @throws InvalidChangeSetException when the value doesn't fit the field
     */
    public function write(EntryInterface $entry, string $localeCode, string $path, mixed $value, string $sourceLocaleCode, string $sourceFingerprint): void;
}
