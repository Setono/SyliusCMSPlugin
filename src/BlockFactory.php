<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin;

use Setono\SyliusCMSPlugin\Doctrine\ORM\BlockRepository;
use Setono\SyliusCMSPlugin\Model\Block;
use Sylius\Component\Resource\Factory\FactoryInterface;

final class BlockFactory implements FactoryInterface
{

    private BlockRepository $repository;

    public function __construct(
        BlockRepository $repository
    )
    {
        $this->repository = $repository;
    }

    public function createNew(): Block
    {
        return new Block();
    }

    public function createNewFromResource(string $resourceId): Block
    {
        /** @var Block $blockToDuplicate */
        $blockToDuplicate = $this->repository->find($resourceId);

        $newBlock = clone $blockToDuplicate;
        $newBlock->setCode(null);

        return $newBlock;
    }
}