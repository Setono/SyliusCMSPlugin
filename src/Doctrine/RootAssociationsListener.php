<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Doctrine;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Setono\SyliusCMSPlugin\Model\Entry;
use Setono\SyliusCMSPlugin\Model\EntryTranslation;

/**
 * A project extends a shipped content type by subclassing its model, such as Page, which then stays a mapped superclass.
 * Sylius's ORMMappedSuperClassSubscriber takes the to-many associations off a resource's mapped superclass, those it
 * inherits from the entity root included, so the subclass would lose the entry's channels, audience groups and
 * translations. This puts the root's associations a content type's model or translation model lacks back on it, as
 * Doctrine inherits them from the root. See "Content types" in docs/spec/extending.md
 */
final readonly class RootAssociationsListener
{
    /** @var array<class-string, class-string> the models and translation models of the content types, to their root */
    private array $roots;

    /**
     * @param array<string, array{model: class-string, translation: class-string|null, resource: string}> $contentTypes content type code => its resource
     */
    public function __construct(array $contentTypes)
    {
        $roots = [];
        foreach ($contentTypes as $resource) {
            $roots[$resource['model']] = Entry::class;
            if (null !== $resource['translation']) {
                $roots[$resource['translation']] = EntryTranslation::class;
            }
        }

        unset($roots[Entry::class], $roots[EntryTranslation::class]);
        $this->roots = $roots;
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $event): void
    {
        /** @var ClassMetadata<object> $metadata */
        $metadata = $event->getClassMetadata();

        $root = $this->roots[$metadata->getName()] ?? null;
        if (null === $root) {
            return;
        }

        foreach ($event->getEntityManager()->getClassMetadata($root)->associationMappings as $field => $mapping) {
            if (isset($metadata->associationMappings[$field])) {
                continue;
            }

            $mapping['inherited'] ??= $root;
            $mapping['declared'] ??= $root;
            $metadata->addInheritedAssociationMapping($mapping);
        }
    }
}
