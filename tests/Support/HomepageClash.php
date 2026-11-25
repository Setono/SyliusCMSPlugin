<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Support;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Setono\SyliusCMSPlugin\Model\HomepageInterface;

/**
 * For functional tests: two saves that make different pages a channel's homepage at the same moment both find no page
 * to take it off, so the second one's row clashes with the first one's on the homepage rows' unique channel code. This
 * onFlush listener plays the first save: as a flush is about to write a page's homepage row, it writes another page's
 * row for the same channel, after the save looked for the channel's homepage
 */
final readonly class HomepageClash
{
    private function __construct(private mixed $otherPageId)
    {
    }

    /**
     * Makes the page with the id the homepage of the channel of every homepage row the manager's flushes write
     */
    public static function withAnotherPage(EntityManagerInterface $manager, mixed $otherPageId): void
    {
        $manager->getEventManager()->addEventListener(Events::onFlush, new self($otherPageId));
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $manager = $args->getObjectManager();
        foreach ($manager->getUnitOfWork()->getScheduledEntityInsertions() as $entity) {
            if ($entity instanceof HomepageInterface) {
                $manager->getConnection()->insert($manager->getClassMetadata($entity::class)->getTableName(), ['channel_code' => $entity->getChannelCode(), 'page_id' => $this->otherPageId]);
            }
        }
    }
}
