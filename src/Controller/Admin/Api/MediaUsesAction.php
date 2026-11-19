<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin\Api;

use Setono\SyliusCMSPlugin\Admin\EntryRows;
use Setono\SyliusCMSPlugin\Controller\Admin\PresetsAction;
use Setono\SyliusCMSPlugin\Model\EditorPresetInterface;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\MediaUsageInterface;
use Setono\SyliusCMSPlugin\Preset\EditorPresetContent;
use Setono\SyliusCMSPlugin\Preset\InvalidPresetException;
use Setono\SyliusCMSPlugin\Preset\TranslationKey;
use Setono\SyliusCMSPlugin\Repository\MediaUsageRepositoryInterface;
use Setono\SyliusCMSPlugin\Security\AdminAccessChecker;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A media item's Used in list, as the usage index has it: each entry that uses it, with its title as the admin's lists
 * show it, its content type, a link to its overview page, and whether its draft and its published content use it, then
 * each editor preset that uses it, with its name and a link to it on the Presets screen. See "Usage index" in
 * docs/spec/media.md
 */
final readonly class MediaUsesAction
{
    public function __construct(
        private AdminAccessChecker $adminAccessChecker,
        private MediaLookup $lookup,
        private MediaUsageRepositoryInterface $usages,
        private EntryRows $entryRows,
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
    ) {
    }

    public function __invoke(string $uuid): JsonResponse
    {
        $this->adminAccessChecker->denyUnlessAdmin();

        $mediaItem = $this->lookup->mediaItem($uuid);

        $uses = $this->usages->findByMediaItem($mediaItem->getUuid());
        $this->entryRows->load(array_values(array_filter(array_map(static fn (MediaUsageInterface $use): ?EntryInterface => $use->getEntry(), $uses))));

        $list = [];
        foreach ($uses as $use) {
            $entry = $use->getEntry();
            $preset = $use->getPreset();
            if (null !== $entry) {
                $list[] = $this->entryUse($entry, $use);
            } elseif (null !== $preset) {
                $list[] = $this->presetUse($preset);
            }
        }

        return new JsonResponse(['uses' => $list]);
    }

    /**
     * @return array<string, mixed>
     */
    private function entryUse(EntryInterface $entry, MediaUsageInterface $use): array
    {
        $row = $this->entryRows->get($entry);

        return [
            'entry' => $row->entry->getUuid(),
            'preset' => null,
            'title' => $row->title,
            'contentType' => $this->translator->trans($row->contentType->label),
            'url' => $this->urlGenerator->generate('setono_sylius_cms_admin_entry_show', ['uuid' => $row->entry->getUuid()]),
            'draft' => $use->isInDraft(),
            'published' => $use->isInPublished(),
        ];
    }

    /**
     * An editor preset has no draft and isn't published: it's a preset, linked to on the Presets screen
     *
     * @return array<string, mixed>
     */
    private function presetUse(EditorPresetInterface $preset): array
    {
        try {
            $content = (new EditorPresetContent())->read($preset);
            $name = TranslationKey::textOf($content->name, $this->translator);
            $tab = null === $content->startingPoint ? PresetsAction::TAB_BLOCK_PRESETS : PresetsAction::TAB_STARTING_POINTS;
        } catch (InvalidPresetException) {
            [$name, $tab] = [null, PresetsAction::TAB_BLOCK_PRESETS];
        }

        return [
            'entry' => null,
            'preset' => $preset->getUuid(),
            'title' => $name,
            'contentType' => $this->translator->trans('setono_sylius_cms.ui.preset'),
            'url' => $this->urlGenerator->generate('setono_sylius_cms_admin_preset_index', ['tab' => $tab, '_fragment' => 'cms-preset-' . $preset->getUuid()]),
            'draft' => false,
            'published' => false,
        ];
    }
}
