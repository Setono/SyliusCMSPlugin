<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\EventListener\Doctrine;

use Doctrine\Persistence\Event\LifecycleEventArgs;
use Setono\SyliusCMSPlugin\Model\ElementInterface;
use Setono\SyliusCMSPlugin\Twig\Loader\LogicalTemplateName;
use Setono\TwigCachePurgerBundle\Purger\PurgerInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;

final class ElementCacheInvalidatorListener
{
    private ChannelRepositoryInterface $channelRepository;

    private PurgerInterface $purger;

    public function __construct(ChannelRepositoryInterface $channelRepository, PurgerInterface $purger)
    {
        $this->channelRepository = $channelRepository;
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
        if (!$entity instanceof ElementInterface) {
            return;
        }

        /** @var ChannelInterface $channel */
        foreach ($this->channelRepository->findAll() as $channel) {
            foreach ($channel->getLocales() as $locale) {
                $this->purger->purge((string) (new LogicalTemplateName(
                    $entity->getType(),
                    (string) $channel->getCode(),
                    (string) $locale->getCode(),
                    (string) $entity->getCode()
                )));
            }
        }
    }
}
