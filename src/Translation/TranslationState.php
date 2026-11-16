<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Translation;

use Setono\SyliusCMSPlugin\Builder\ChangeSet\ChangeSet;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\SetEntryTranslated;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\SetEntryValue;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\SetTranslated;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\SetValue;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Publishing\AutosaveInterface;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyProviderInterface;

final readonly class TranslationState implements TranslationStateInterface
{
    public function __construct(
        private WorkingCopyProviderInterface $workingCopies,
        private AutosaveInterface $autosave,
        private TranslatableValues $translatableValues,
    ) {
    }

    public function values(EntryInterface $entry, string $localeCode): iterable
    {
        return $this->translatableValues->of($this->workingCopies->getWorkingCopy($entry), $localeCode);
    }

    public function write(EntryInterface $entry, string $localeCode, string $path, mixed $value, string $sourceLocaleCode, string $sourceFingerprint): void
    {
        if (!Fingerprint::isValid($sourceFingerprint)) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a fingerprint: a lowercase hex SHA-256', $sourceFingerprint));
        }

        $source = ['from' => $sourceLocaleCode, 'fingerprint' => $sourceFingerprint];
        $changes = match (true) {
            1 === preg_match('/^entry\.(\w+)$/D', $path, $matches) => [
                new SetEntryValue($matches[1], $localeCode, $value),
                new SetEntryTranslated($matches[1], $localeCode, $source),
            ],
            1 === preg_match('/^block\.([0-9a-f-]+)\.(\w+)$/D', $path, $matches) => [
                new SetValue($matches[1], $matches[2], $localeCode, $value),
                new SetTranslated($matches[1], $matches[2], $localeCode, $source),
            ],
            // Layout fields are written once the builder edits layouts, with the change it saves them with
            default => throw new \InvalidArgumentException(sprintf('The path "%s" is not one of an entry field (entry.<field>) or a block field (block.<key>.<field>)', $path)),
        };

        // The latest lock token, so the save goes on top of what editors saved, as their own saves do
        $this->autosave->save($entry, new ChangeSet($changes), $this->autosave->load($entry)->lockToken);
    }
}
