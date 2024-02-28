<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\EventListener;

use Doctrine\Persistence\Event\LifecycleEventArgs;
use Setono\SyliusCMSPlugin\Model\ElementInterface;
use Setono\SyliusCMSPlugin\Twig\Loader\LogicalTemplateName;
use Setono\TwigCachePurgerBundle\Purger\PurgerInterface;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
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

    /**
     * @param mixed $args
     */
    public function postUpdate($args): void
    {
        $this->invalidateCache($args);
    }

    /**
     * @param mixed $args
     */
    public function postRemove($args): void
    {
        $this->invalidateCache($args);
    }

    /**
     * @param mixed $args
     */
    private function invalidateCache($args): void
    {
        $entity = null;

        if ($args instanceof LifecycleEventArgs) {
            $entity = $args->getObject();
        } elseif ($args instanceof ResourceControllerEvent) {
            /** @var mixed $entity */
            $entity = $args->getSubject();
        }

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
                    (string) $entity->getCode(),
                )));
            }
        }
    }
}
