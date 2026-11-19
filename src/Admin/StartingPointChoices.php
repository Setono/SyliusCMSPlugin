<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

use Setono\SyliusCMSPlugin\Preset\Preset;
use Setono\SyliusCMSPlugin\Preset\PresetCheck;
use Setono\SyliusCMSPlugin\Preset\PresetIssue;
use Setono\SyliusCMSPlugin\Preset\PresetProviderInterface;
use Setono\SyliusCMSPlugin\Preset\TranslationKey;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The starting points the create dialog offers for a content type, besides Blank: code presets and editor presets, in
 * the order they're provided, each with its texts in the admin user's locale and its thumbnail. The code preset marked
 * as the content type's default is the dialog's default. What's wrong with a starting point is said, and one with
 * blocks of types the code no longer has can't be picked. See "Starting points" in docs/spec/layouts-and-presets.md and
 * "Create dialog" in docs/spec/admin.md
 */
final readonly class StartingPointChoices
{
    public function __construct(
        private PresetProviderInterface $presets,
        private PresetCheck $check,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @param string $contentType a content type's code
     *
     * @return list<StartingPointChoice>
     */
    public function of(string $contentType): array
    {
        $choices = [];
        foreach ($this->presets->all() as $preset) {
            if ($preset->startingPoint?->contentType === $contentType) {
                $choices[] = $this->choice($preset);
            }
        }

        return $choices;
    }

    private function choice(Preset $preset): StartingPointChoice
    {
        $issues = $this->check->check($preset);

        return new StartingPointChoice(
            $preset,
            (string) $this->text($preset->name),
            $this->text($preset->description),
            $preset->thumbnail,
            // Editors can't make a starting point the default
            $preset->isFromCode() && true === $preset->startingPoint?->default,
            array_values(array_unique(array_map(fn (PresetIssue $issue): string => $issue->message->trans($this->translator), $issues))),
            [] !== array_filter($issues, static fn (PresetIssue $issue): bool => $issue->kind->disablesStartingPoint()),
        );
    }

    private function text(string|TranslationKey|null $text): ?string
    {
        return TranslationKey::textOf($text, $this->translator);
    }
}
