<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\EventListener\Doctrine;

use Doctrine\Persistence\Event\LifecycleEventArgs;
use Psr\EventDispatcher\EventDispatcherInterface;
use Setono\SyliusCMSPlugin\Event\CacheInvalidatedEvent;
use Setono\SyliusCMSPlugin\Generator\ElementCacheKeyGeneratorInterface;
use Setono\SyliusCMSPlugin\Model\ElementInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Contracts\Cache\CacheInterface;

final class ElementCacheInvalidatorListener
{
    private CacheInterface $cachePool;

    private ElementCacheKeyGeneratorInterface $elementCacheKeyGenerator;

    private ChannelRepositoryInterface $channelRepository;

    private EventDispatcherInterface $eventDispatcher;

    public function __construct(
        CacheInterface $cachePool,
        ElementCacheKeyGeneratorInterface $elementCacheKeyGenerator,
        ChannelRepositoryInterface $channelRepository,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->cachePool = $cachePool;
        $this->elementCacheKeyGenerator = $elementCacheKeyGenerator;
        $this->channelRepository = $channelRepository;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function postPersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof ElementInterface) {
            return;
        }

        $this->invalidateCache($entity);
    }

    public function postUpdate(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof ElementInterface) {
            return;
        }

        $this->invalidateCache($entity);
    }

    private function invalidateCache(ElementInterface $element): void
    {
        /** @var ChannelInterface $channel */
        foreach ($this->channelRepository->findAll() as $channel) {
            foreach ($channel->getLocales() as $locale) {
                $cacheKey = $this->elementCacheKeyGenerator->generateCacheKey(
                    $element,
                    null,
                    $channel,
                    $locale->getCode()
                );

                try {
                    $this->cachePool->delete($cacheKey);
                } catch (\Throwable $e) {
                    // Ignore because it means the cache does not exist yet
                }
            }
        }

        $this->eventDispatcher->dispatch(new CacheInvalidatedEvent($element));
    }
}
