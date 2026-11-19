<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Preset;

use Symfony\Component\Translation\TranslatableMessage;

/**
 * What editors organise presets by: a name, an optional category, which is free text, and an optional description.
 * They're given when ★ saves a preset, and changed when an editor renames or recategorises one. See "Editor presets" in
 * docs/spec/layouts-and-presets.md
 */
final readonly class PresetDetails
{
    private const FIELDS = ['name', 'category', 'description'];

    public function __construct(
        public string|TranslationKey $name,
        public string|TranslationKey|null $category = null,
        public string|TranslationKey|null $description = null,
    ) {
    }

    public static function of(Preset $preset): self
    {
        return new self($preset->name, $preset->category, $preset->description);
    }

    /**
     * The details with what the input changes: each of name, category and description it has, trimmed. An empty
     * category or description, or null, is none
     *
     * @param array<array-key, mixed> $input
     *
     * @throws InvalidPresetDetailsException for a value that isn't text, or no name
     */
    public function changedBy(array $input): self
    {
        $changed = [];
        foreach (self::FIELDS as $field) {
            if (!array_key_exists($field, $input)) {
                continue;
            }

            $value = $input[$field];
            if (null !== $value && !is_string($value)) {
                throw new InvalidPresetDetailsException(new TranslatableMessage('setono_sylius_cms.preset.details_not_text'));
            }

            $value = trim((string) $value);
            $changed[$field] = '' === $value ? null : $value;
        }

        $name = array_key_exists('name', $changed) ? $changed['name'] : $this->name;
        if (null === $name || '' === $name) {
            throw new InvalidPresetDetailsException(new TranslatableMessage('setono_sylius_cms.preset.name_required'));
        }

        return new self(
            $name,
            array_key_exists('category', $changed) ? $changed['category'] : $this->category,
            array_key_exists('description', $changed) ? $changed['description'] : $this->description,
        );
    }

    /**
     * The preset with these details, and nothing else changed
     */
    public function applyTo(Preset $preset): Preset
    {
        return new Preset(
            $this->name,
            $preset->blocks,
            $this->category,
            $this->description,
            $preset->startingPoint,
            $preset->code,
            $preset->thumbnail,
            $preset->uuid,
            $preset->changedAt,
        );
    }
}
