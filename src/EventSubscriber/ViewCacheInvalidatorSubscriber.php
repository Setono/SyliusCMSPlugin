<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\EventSubscriber;

use Psr\EventDispatcher\EventDispatcherInterface;
use Setono\SyliusCMSPlugin\Event\CacheInvalidatedEvent;
use Setono\SyliusCMSPlugin\Generator\ElementCacheKeyGeneratorInterface;
use Setono\SyliusCMSPlugin\Model\BlockInterface;
use Setono\SyliusCMSPlugin\Model\TemplateInterface;
use Setono\SyliusCMSPlugin\Model\ViewInterface;
use Setono\SyliusCMSPlugin\Repository\ViewRepositoryInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * A view should be invalidated when
 * - a block within the view is updated
 * - the template the view uses is updated
 */
final class ViewCacheInvalidatorSubscriber implements EventSubscriberInterface
{
    private ViewRepositoryInterface $viewRepository;

    private ChannelRepositoryInterface $channelRepository;

    private ElementCacheKeyGeneratorInterface $elementCacheKeyGenerator;

    private CacheInterface $cachePool;

    private EventDispatcherInterface $eventDispatcher;

    public function __construct(
        ViewRepositoryInterface $viewRepository,
        ChannelRepositoryInterface $channelRepository,
        ElementCacheKeyGeneratorInterface $elementCacheKeyGenerator,
        CacheInterface $cachePool,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->viewRepository = $viewRepository;
        $this->channelRepository = $channelRepository;
        $this->elementCacheKeyGenerator = $elementCacheKeyGenerator;
        $this->cachePool = $cachePool;
        $this->eventDispatcher = $eventDispatcher;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CacheInvalidatedEvent::class => 'invalidate',
        ];
    }

    public function invalidate(CacheInvalidatedEvent $event): void
    {
        if ($event->resource instanceof TemplateInterface) {
            $this->invalidateViews($this->viewRepository->findByTemplate($event->resource));
        }

        if ($event->resource instanceof BlockInterface) {
            $this->invalidateViews($this->viewRepository->findByBlock($event->resource));
        }
    }

    /**
     * @param ViewInterface[] $views
     */
    private function invalidateViews(array $views): void
    {
        /** @var list<ChannelInterface> $channels */
        $channels = $this->channelRepository->findAll();

        foreach ($views as $view) {
            foreach ($channels as $channel) {
                foreach ($channel->getLocales() as $locale) {
                    $cacheKey = $this->elementCacheKeyGenerator->generateCacheKey($view, null, $channel, $locale->getCode());

                    try {
                        $this->cachePool->delete($cacheKey);
                    } catch (\Throwable $e) {
                        // Ignore because it means the cache does not exist
                    }
                }
            }

            $this->eventDispatcher->dispatch(new CacheInvalidatedEvent($view));
        }
    }
}
