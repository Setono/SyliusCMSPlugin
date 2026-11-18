<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Doctrine;

use Doctrine\ORM\Event\PrePersistEventArgs;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;

/**
 * A new entry of a content type ordered by position goes last among its entries, however it's created, until editors
 * drag it elsewhere in the admin list. An entry that comes with a position, such as an imported one, keeps it
 */
final readonly class EntryPositionListener
{
    public function __construct(private ContentTypeRegistryInterface $contentTypes)
    {
    }

    public function prePersist(PrePersistEventArgs $event): void
    {
        $entry = $event->getObject();
        if (!$entry instanceof EntryInterface || null !== $entry->getPosition()) {
            return;
        }

        $contentType = $this->contentTypes->getByEntry($entry);
        if (!$contentType->order->byPosition) {
            return;
        }

        $last = $event->getObjectManager()->createQueryBuilder()
            ->select('MAX(o.position)')
            ->from($contentType->modelClass, 'o')
            ->getQuery()
            ->getSingleScalarResult()
        ;

        $entry->setPosition(null === $last ? 0 : (int) $last + 1);
    }
}
