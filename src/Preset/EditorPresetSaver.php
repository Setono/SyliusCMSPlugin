<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Preset;

use Doctrine\Persistence\ManagerRegistry;
use Psr\Clock\ClockInterface;
use Setono\SyliusCMSPlugin\Doctrine\EntityManagers;
use Setono\SyliusCMSPlugin\Model\EditorPresetInterface;
use Setono\SyliusCMSPlugin\Repository\EditorPresetRepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;

/**
 * Writes the preset to its row in the preset file format, through EditorPresetContent. changedAt is kept to the second,
 * as the format writes it. The media usage index follows in the same flush (Media\MediaUsageListener)
 */
final readonly class EditorPresetSaver implements EditorPresetSaverInterface
{
    /**
     * @param FactoryInterface<EditorPresetInterface> $factory
     */
    public function __construct(
        private EditorPresetRepositoryInterface $repository,
        private FactoryInterface $factory,
        private ManagerRegistry $managerRegistry,
        private ClockInterface $clock,
    ) {
    }

    public function save(Preset $preset, ?\DateTimeImmutable $changedAt = null): Preset
    {
        $row = null === $preset->uuid ? null : $this->repository->findOneByUuid($preset->uuid);
        if (null === $row) {
            $row = $this->factory->createNew();
            if (null !== $preset->uuid) {
                $row->setUuid($preset->uuid);
            }
        }

        $changedAt = ($changedAt ?? $this->clock->now())->setTimezone(new \DateTimeZone('UTC'));
        $saved = new Preset(
            $preset->name,
            $preset->blocks,
            $preset->category,
            $preset->description,
            $preset->startingPoint,
            uuid: $row->getUuid(),
            changedAt: new \DateTimeImmutable($changedAt->format('Y-m-d H:i:s'), new \DateTimeZone('UTC')),
        );
        (new EditorPresetContent())->write($row, $saved);

        $manager = EntityManagers::of($this->managerRegistry, $row::class);
        $manager->persist($row);
        $manager->flush();

        return $saved;
    }
}
