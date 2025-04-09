<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\EventListener;

use Doctrine\Persistence\Event\LifecycleEventArgs;
use Setono\SyliusCMSPlugin\Model\Element;
use Setono\SyliusCMSPlugin\Model\ElementInterface;
use Setono\SyliusCMSPlugin\Twig\LogicalTemplateName;
use Setono\TwigCachePurgerBundle\Purger\PurgerInterface;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Resource\Model\TranslationInterface;
use Webmozart\Assert\Assert;

final class ElementCacheInvalidatorListener
{
    /** @var array<array-key, ChannelInterface>|null */
    private ?array $channels = null;

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

        foreach ($this->getChannels() as $channel) {
            foreach ($channel->getLocales() as $locale) {
                $this->purger->purge((string) (new LogicalTemplateName(
                    Element::getType($entity),
                    (string) $channel->getCode(),
                    (string) $locale->getCode(),
                    (string) $entity->getCode(),
                )));
            }
        }
    }

    /**
     * @return array<array-key, ChannelInterface>
     */
    private function getChannels(): array
    {
        if (null === $this->channels) {
            $channels = $this->channelRepository->findAll();
            Assert::allIsInstanceOf($channels, ChannelInterface::class);

            $this->channels = $channels;
        }

        return $this->channels;
    }
}
