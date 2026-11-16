<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Factory;

use Setono\SyliusCMSPlugin\Model\HomepageInterface;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Sylius\Resource\Exception\UnsupportedMethodException;

final readonly class HomepageFactory implements HomepageFactoryInterface
{
    /**
     * @param class-string<HomepageInterface> $className the homepage resource's model
     */
    public function __construct(private string $className)
    {
    }

    /**
     * A row always belongs to a page and has a channel code
     *
     * @throws UnsupportedMethodException
     */
    public function createNew(): HomepageInterface
    {
        throw new UnsupportedMethodException('createNew');
    }

    public function createForPage(PageInterface $page, string $channelCode): HomepageInterface
    {
        return new $this->className($page, $channelCode);
    }
}
