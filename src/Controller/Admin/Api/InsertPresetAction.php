<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin\Api;

use Setono\SyliusCMSPlugin\Json\CanonicalJson;
use Setono\SyliusCMSPlugin\Json\ListedObject;
use Setono\SyliusCMSPlugin\Preset\InsertTransformer;
use Setono\SyliusCMSPlugin\Preset\PresetProviderInterface;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyProviderInterface;
use Setono\SyliusCMSPlugin\Tree\StoredTreeSerializer;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A preset's blocks, by its id (a code preset's code or an editor preset's UUID), ready to insert into the entry the
 * body names, {"entry": <uuid>}: upgraded, with new keys, translation keys resolved for the entry's locales, and the
 * blocks of removed or disabled types left out, with a notice. A block newer than the code's refuses the insert, 422
 * with the notice. The builder inserts the blocks it answers with, and saves them as any edit, so this changes nothing
 * and needs no CSRF token. See "What inserting copies" in docs/spec/layouts-and-presets.md and "JSON endpoints" in
 * docs/spec/builder.md
 */
final readonly class InsertPresetAction
{
    public function __construct(
        private BuilderRequest $builderRequest,
        private WorkingCopyProviderInterface $workingCopies,
        private PresetProviderInterface $presets,
        private InsertTransformer $insertTransformer,
        private TranslatorInterface $translator,
    ) {
    }

    public function __invoke(Request $request, string $preset): JsonResponse
    {
        try {
            $uuid = $request->toArray()['entry'] ?? null;
        } catch (JsonException $e) {
            return BuilderRequest::badRequest($e->getMessage());
        }

        if (!is_string($uuid) || '' === $uuid) {
            return BuilderRequest::badRequest('The body needs the entry to insert into, as {"entry": <uuid>}');
        }

        $workingCopy = $this->workingCopies->getWorkingCopy($this->builderRequest->entry($uuid));
        $found = $this->presets->find($preset) ?? throw new NotFoundHttpException(sprintf('There is no preset %s', $preset));

        $inserted = $this->insertTransformer->transform($found, array_values(array_map(strval(...), $workingCopy->getTranslations()->getKeys())));
        $notices = array_map(fn (TranslatableMessage $notice): string => $notice->trans($this->translator), $inserted->notices);
        if ($inserted->refused) {
            return new JsonResponse(['error' => implode(' ', $notices), 'notices' => $notices], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse(CanonicalJson::encode(new ListedObject([
            'blocks' => array_map((new StoredTreeSerializer())->normalizeBlock(...), $inserted->blocks),
            'notices' => $notices,
        ])), json: true);
    }
}
