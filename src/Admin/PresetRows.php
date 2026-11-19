<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

use Setono\SyliusCMSPlugin\Preset\Preset;
use Setono\SyliusCMSPlugin\Preset\PresetCheck;
use Setono\SyliusCMSPlugin\Preset\PresetIssue;
use Setono\SyliusCMSPlugin\Preset\PresetProviderInterface;
use Setono\SyliusCMSPlugin\Preset\TranslationKey;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * What the Presets screen lists, in the order the presets are provided: code presets first, then editor presets, the
 * one saved first first. Each preset's texts are translated into the admin user's locale, and what's wrong with its
 * blocks flags it. See "Presets" in docs/spec/admin.md
 */
final readonly class PresetRows
{
    public function __construct(
        private PresetProviderInterface $presets,
        private PresetCheck $check,
        private ContentTypeRegistryInterface $contentTypes,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * The starting points, or the block presets
     *
     * @return list<PresetRow>
     */
    public function rows(bool $startingPoints): array
    {
        $rows = [];
        foreach ($this->presets->all() as $preset) {
            if ((null !== $preset->startingPoint) === $startingPoints) {
                $rows[] = $this->row($preset);
            }
        }

        return $rows;
    }

    /**
     * The categories in use, of code presets and editor presets, which editors are suggested when they give a preset one
     *
     * @return list<string>
     */
    public function categories(): array
    {
        $categories = [];
        foreach ($this->presets->all() as $preset) {
            $category = $this->text($preset->category);
            if (null !== $category) {
                $categories[$category] = true;
            }
        }

        $categories = array_map(strval(...), array_keys($categories));
        sort($categories, \SORT_NATURAL | \SORT_FLAG_CASE);

        return $categories;
    }

    private function row(Preset $preset): PresetRow
    {
        $contentType = $preset->startingPoint?->contentType;

        return new PresetRow(
            $preset,
            (string) $this->text($preset->name),
            $this->text($preset->category),
            $this->text($preset->description),
            null === $contentType ? null : ($this->contentTypes->has($contentType) ? $this->translator->trans($this->contentTypes->get($contentType)->label) : $contentType),
            array_values(array_unique(array_map(fn (PresetIssue $issue): string => $issue->message->trans($this->translator), $this->check->check($preset)))),
        );
    }

    private function text(string|TranslationKey|null $text): ?string
    {
        return TranslationKey::textOf($text, $this->translator);
    }
}
