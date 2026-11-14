<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Doctrine;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Setono\SyliusCMSPlugin\Model\Entry;
use Setono\SyliusCMSPlugin\Model\EntryTranslation;

/**
 * Builds both discriminator maps from the registered content types, so projects never edit the plugin's mapping (ADR 0001).
 * Subclasses copy their root's map when Doctrine loads them, so it's enough to add to the roots'
 */
final readonly class DiscriminatorMapListener
{
    /**
     * @param array<string, array{model: class-string, translation: class-string|null, resource: string}> $contentTypes content type code => its resource
     */
    public function __construct(private array $contentTypes)
    {
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $event): void
    {
        $metadata = $event->getClassMetadata();

        if (Entry::class === $metadata->getName()) {
            $metadata->setDiscriminatorMap(array_map(static fn (array $resource): string => $resource['model'], $this->contentTypes));

            return;
        }

        if (EntryTranslation::class === $metadata->getName()) {
            $map = [];
            foreach ($this->contentTypes as $code => $resource) {
                if (null !== $resource['translation'] && EntryTranslation::class !== $resource['translation']) {
                    $map[$code] = $resource['translation'];
                }
            }

            $metadata->setDiscriminatorMap($map);
        }
    }
}
