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
use Sylius\Component\Resource\Model\TranslationInterface;

final class ElementCacheInvalidatorListener
{
    public function __construct(
        private readonly ChannelRepositoryInterface $channelRepository,
        private readonly PurgerInterface $purger,
    ) {
    }

    public function postPersist(LifecycleEventArgs $args): void
    {
        $this->invalidateCache($args);
    }

    public function postUpdate(mixed $args): void
    {
        $this->invalidateCache($args);
    }

    public function postRemove(mixed $args): void
    {
        $this->invalidateCache($args);
    }

    private function invalidateCache(mixed $args): void
    {
        $entity = null;

        if ($args instanceof LifecycleEventArgs) {
            $entity = $args->getObject();
        } elseif ($args instanceof ResourceControllerEvent) {
            /** @var mixed $entity */
            $entity = $args->getSubject();
        }

        if ($entity instanceof TranslationInterface) {
            $entity = $entity->getTranslatable();
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
