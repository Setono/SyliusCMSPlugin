<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Factory;

use Setono\SyliusCMSPlugin\Model\BlockInterface;
use Setono\SyliusCMSPlugin\Repository\BlockRepositoryInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Webmozart\Assert\Assert;

final class BlockFactory implements BlockFactoryInterface
{
    private BlockRepositoryInterface $repository;

    private FactoryInterface $decoratedFactory;

    public function __construct(
        FactoryInterface $decoratedFactory,
        BlockRepositoryInterface $repository
    ) {
        $this->decoratedFactory = $decoratedFactory;
        $this->repository = $repository;
    }

    public function createNew(): BlockInterface
    {
        /** @var BlockInterface $block */
        $block = $this->decoratedFactory->createNew();
        Assert::isInstanceOf($block, BlockInterface::class);

        return $block;
    }

    public function createNewFromResource($resource): BlockInterface
    {
        if (is_numeric($resource)) {
            $resource = $this->repository->find($resource);
        }

        Assert::isInstanceOf($resource, BlockInterface::class);

        $newBlock = clone $resource;
        $newBlock->setCode((string) $newBlock->getCode() . '_copy');

        return $newBlock;
    }
}
