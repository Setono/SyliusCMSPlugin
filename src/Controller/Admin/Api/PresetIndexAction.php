<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin\Api;

use Setono\SyliusCMSPlugin\Json\CanonicalJson;
use Setono\SyliusCMSPlugin\Json\ListedObject;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Preset\InsertTransformer;
use Setono\SyliusCMSPlugin\Preset\Preset;
use Setono\SyliusCMSPlugin\Preset\PresetCheck;
use Setono\SyliusCMSPlugin\Preset\PresetFitCheck;
use Setono\SyliusCMSPlugin\Preset\PresetIssue;
use Setono\SyliusCMSPlugin\Preset\PresetProviderInterface;
use Setono\SyliusCMSPlugin\Preset\TranslationKey;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyProviderInterface;
use Setono\SyliusCMSPlugin\Tree\StoredTreeSerializer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The block presets for the block picker's Presets tab, at a spot in an entry's tree: ?entry=<uuid>, and &parent=<key>
 * for the children of a block, or none for the top level. Each comes with its name, category and description in the
 * admin user's locale, whether it's from code, whether it's offered at the spot and why not, why it's marked invalid,
 * and the blocks it would insert, which the tab renders live under the preview's token. It reads the working copy as
 * the server has it, so the builder sends its unsaved changes first. It changes nothing, so it needs no CSRF token.
 * Starting points are offered when an entry is created, not here. See "Where a preset is offered" in
 * docs/spec/layouts-and-presets.md and "Block picker" in docs/spec/builder.md
 */
final readonly class PresetIndexAction
{
    public function __construct(
        private BuilderRequest $builderRequest,
        private WorkingCopyProviderInterface $workingCopies,
        private PresetProviderInterface $presets,
        private InsertTransformer $insertTransformer,
        private PresetFitCheck $fitCheck,
        private PresetCheck $presetCheck,
        private TranslatorInterface $translator,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $uuid = $request->query->get('entry');
        if (!is_string($uuid) || '' === $uuid) {
            return BuilderRequest::badRequest('The query needs the entry, as ?entry=<uuid>');
        }

        $workingCopy = $this->workingCopies->getWorkingCopy($this->builderRequest->entry($uuid));
        $parent = $request->query->get('parent');
        $parent = is_string($parent) && '' !== $parent ? $parent : null;
        $locales = self::localesOf($workingCopy);

        $presets = [];
        foreach ($this->presets->all() as $preset) {
            if (null === $preset->startingPoint) {
                $presets[] = $this->describe($preset, $workingCopy, $parent, $locales);
            }
        }

        return new JsonResponse(CanonicalJson::encode(new ListedObject(['presets' => $presets])), json: true);
    }

    /**
     * @param list<string> $locales
     */
    private function describe(Preset $preset, EntryInterface $workingCopy, ?string $parent, array $locales): ListedObject
    {
        $inserted = $this->insertTransformer->transform($preset, $locales);
        $reasons = $inserted->refused ? $inserted->notices : $this->fitCheck->at($inserted->blocks, $workingCopy, $parent)->reasons;
        $serializer = new StoredTreeSerializer();

        return new ListedObject([
            'id' => $preset->id(),
            'fromCode' => $preset->isFromCode(),
            'name' => $this->text($preset->name),
            'category' => $this->text($preset->category),
            'description' => $this->text($preset->description),
            'thumbnail' => $preset->thumbnail,
            'fits' => [] === $reasons,
            'reasons' => array_map($this->trans(...), $reasons),
            'issues' => array_values(array_unique(array_map(fn (PresetIssue $issue): string => $this->trans($issue->message), $this->presetCheck->check($preset)))),
            'blocks' => array_map($serializer->normalizeBlock(...), $inserted->blocks),
        ]);
    }

    private function text(string|TranslationKey|null $text): ?string
    {
        return $text instanceof TranslationKey ? $this->translator->trans($text->key, [], $text->domain) : $text;
    }

    private function trans(TranslatableMessage $message): string
    {
        return $message->trans($this->translator);
    }

    /**
     * @return list<string> the locales the entry has a translation in
     */
    private static function localesOf(EntryInterface $entry): array
    {
        return array_values(array_map(strval(...), $entry->getTranslations()->getKeys()));
    }
}
