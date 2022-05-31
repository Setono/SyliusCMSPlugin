<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\EventListener\Doctrine;

use Doctrine\Persistence\Event\LifecycleEventArgs;
use Setono\SyliusCMSPlugin\Model\TemplateInterface;
use Setono\TwigCachePurgerBundle\Purger\PurgerInterface;

final class TemplateCacheInvalidatorListener
{
    private PurgerInterface $purger;

    public function __construct(PurgerInterface $purger)
    {
        $this->purger = $purger;
    }

    public function postPersist(LifecycleEventArgs $args): void
    {
        $this->invalidateCache($args);
    }

    public function postUpdate(LifecycleEventArgs $args): void
    {
        $this->invalidateCache($args);
    }

    private function invalidateCache(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof TemplateInterface) {
            return;
        }

        $this->purger->purge((string) $entity->getCode());
    }
}
