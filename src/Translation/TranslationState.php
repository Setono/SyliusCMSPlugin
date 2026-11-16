<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Translation;

use Setono\SyliusCMSPlugin\Builder\ChangeSet\Change;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\ChangeSet;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\SetEntryTranslated;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\SetEntryValue;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\SetLayoutTranslated;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\SetLayoutValue;
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

        // The latest lock token, so the save goes on top of what editors saved, as their own saves do
        $loaded = $this->autosave->load($entry);
        $source = ['from' => $sourceLocaleCode, 'fingerprint' => $sourceFingerprint];

        $this->autosave->save($entry, new ChangeSet(self::changes($loaded->workingCopy, $localeCode, $path, $value, $source)), $loaded->lockToken);
    }

    /**
     * @param array{from: string, fingerprint: string} $source
     *
     * @return list<Change>
     */
    private static function changes(EntryInterface $workingCopy, string $localeCode, string $path, mixed $value, array $source): array
    {
        if (1 === preg_match('/^entry\.(\w+)$/D', $path, $matches)) {
            return [new SetEntryValue($matches[1], $localeCode, $value), new SetEntryTranslated($matches[1], $localeCode, $source)];
        }

        if (1 === preg_match('/^block\.([0-9a-f-]+)\.(\w+)$/D', $path, $matches)) {
            return [new SetValue($matches[1], $matches[2], $localeCode, $value), new SetTranslated($matches[1], $matches[2], $localeCode, $source)];
        }

        if (1 === preg_match('/^layout\.(\w+)$/D', $path, $matches)) {
            // Set in the layout the draft is in, as the builder sets an editor's change
            $layout = $workingCopy->getBlockTree()->layout?->code
                ?? throw new \InvalidArgumentException(sprintf('The path "%s" is a layout field, but the entry\'s draft has no layout', $path));

            return [new SetLayoutValue($layout, $matches[1], $localeCode, $value), new SetLayoutTranslated($layout, $matches[1], $localeCode, $source)];
        }

        throw new \InvalidArgumentException(sprintf('The path "%s" is not one of an entry field (entry.<field>), a layout field (layout.<field>) or a block field (block.<key>.<field>)', $path));
    }
}
