<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Factory;

use Setono\SyliusCMSPlugin\Model\Homepage;
use Setono\SyliusCMSPlugin\Model\Page;
use Sylius\Resource\Exception\UnsupportedMethodException;

final readonly class HomepageFactory implements HomepageFactoryInterface
{
    /**
     * @param class-string<Homepage> $className the homepage resource's model
     */
    public function __construct(private string $className)
    {
    }

    /**
     * A row always belongs to a page and has a channel code
     *
     * @throws UnsupportedMethodException
     */
    public function createNew(): Homepage
    {
        throw new UnsupportedMethodException('createNew');
    }

    public function createForPage(Page $page, string $channelCode): Homepage
    {
        return new $this->className($page, $channelCode);
    }
}
