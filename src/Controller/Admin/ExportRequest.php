<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin;

use Setono\SyliusCMSPlugin\Repository\EditorPresetRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\MediaFolderRepositoryInterface;
use Setono\SyliusCMSPlugin\Transfer\ExportSelection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Uuid;

/**
 * What the Transfer screen's export form picks, in its query: entries[], presets[] and folders[] by UUID, and all for
 * everything, less the entry a Remove button names. The form is a GET form, so the screen and its Download read the
 * same query. A record that no longer exists, such as one deleted since it was picked, is left out
 *
 * @internal
 */
final readonly class ExportRequest
{
    public function __construct(
        private EntryRepositoryInterface $entryRepository,
        private EditorPresetRepositoryInterface $presetRepository,
        private MediaFolderRepositoryInterface $folderRepository,
    ) {
    }

    public function selection(Request $request): ExportSelection
    {
        $remove = $request->query->get('remove');
        $entries = array_values(array_diff(self::uuids($request, 'entries'), [is_string($remove) ? strtolower($remove) : '']));

        return new ExportSelection(
            entries: array_values(array_intersect($entries, $this->entryRepository->findExistingUuids($entries))),
            mediaFolders: array_values(array_filter(self::uuids($request, 'folders'), fn (string $uuid): bool => null !== $this->folderRepository->findOneByUuid($uuid))),
            presets: array_values(array_filter(self::uuids($request, 'presets'), fn (string $uuid): bool => null !== $this->presetRepository->findOneByUuid($uuid))),
            everything: $request->query->getBoolean('all'),
        );
    }

    /**
     * The UUIDs in the query parameter's list, each once, lowercase. What isn't a UUID is left out
     *
     * @return list<string>
     */
    private static function uuids(Request $request, string $key): array
    {
        $values = $request->query->all()[$key] ?? [];
        if (!is_array($values)) {
            return [];
        }

        $uuids = [];
        foreach ($values as $value) {
            if (is_string($value) && Uuid::isValid($value)) {
                $uuids[strtolower($value)] = true;
            }
        }

        return array_map(strval(...), array_keys($uuids));
    }
}
