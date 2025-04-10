<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\EventListener\Doctrine;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Setono\SyliusCMSPlugin\Model\Slide;
use Setono\SyliusCMSPlugin\Model\SlideInterface;
use function Symfony\Component\String\u;

final class SlideDiscriminatorMapListener
{
    public function __construct(
        /** @var array<string, array{classes: array{model: class-string}}> $resources */
        private readonly array $resources,
    ) {
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs): void
    {
        $metadata = $eventArgs->getClassMetadata();
        if ($metadata->getName() !== Slide::class) {
            return;
        }

        $metadata->discriminatorMap = $this->getDiscriminatorMap();
    }

    /**
     * @return array<string, class-string>
     */
    private function getDiscriminatorMap(): array
    {
        $children = [];

        foreach ($this->resources as $resource) {
            ['model' => $model] = $resource['classes'];

            // todo this is a naïve approach. We need to find child entities of either Slide or SlideInterface
            if (Slide::class === $model || !is_a($model, SlideInterface::class, true)) {
                continue;
            }

            $children[self::getDiscriminatorKey($model)] = $model;
        }

        return $children;
    }

    /**
     * @param class-string $class
     */
    private static function getDiscriminatorKey(string $class): string
    {
        return u((new \ReflectionClass($class))->getShortName())->snake()->toString();
    }
}
