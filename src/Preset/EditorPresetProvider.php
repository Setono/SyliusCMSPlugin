<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Preset;

use Psr\Log\LoggerInterface;
use Setono\SyliusCMSPlugin\Model\EditorPresetInterface;
use Setono\SyliusCMSPlugin\Repository\EditorPresetRepositoryInterface;

/**
 * The editor presets, the one saved first first, read from their rows. One whose content isn't in the preset file
 * format, which only a hand-made change to the database gives, is left out and logged. See "Editor presets" in
 * docs/spec/layouts-and-presets.md
 */
final readonly class EditorPresetProvider implements PresetProviderInterface
{
    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    public function __construct(
        private EditorPresetRepositoryInterface $repository,
        private LoggerInterface $logger,
    ) {
    }

    public function all(): array
    {
        $presets = [];
        foreach ($this->repository->findAllInCreationOrder() as $row) {
            $preset = $this->read($row);
            if (null !== $preset) {
                $presets[] = $preset;
            }
        }

        return $presets;
    }

    public function find(string $id): ?Preset
    {
        // A code preset's code is no UUID, so it isn't looked for
        if (1 !== preg_match(self::UUID_PATTERN, $id)) {
            return null;
        }

        $row = $this->repository->findOneByUuid($id);

        return null === $row ? null : $this->read($row);
    }

    private function read(EditorPresetInterface $row): ?Preset
    {
        try {
            return (new EditorPresetContent())->read($row);
        } catch (InvalidPresetException $e) {
            $this->logger->warning('The editor preset {uuid} can\'t be read, so editors aren\'t offered it: {message}', [
                'uuid' => $row->getUuid(),
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
