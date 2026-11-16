<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Factory;

use Setono\SyliusCMSPlugin\Model\Homepage;
use Setono\SyliusCMSPlugin\Model\Page;
use Sylius\Resource\Factory\FactoryInterface;

/**
 * The homepage resource's factory: it creates the rows of the configured model, so a project that overrides the model
 * gets rows of its own class. The model extends Homepage, whose Doctrine mapping it inherits.
 * Change the channels a page is the homepage of through HomepageModifierInterface
 *
 * @extends FactoryInterface<Homepage>
 */
interface HomepageFactoryInterface extends FactoryInterface
{
    /**
     * The row that makes $page the homepage of the channel with the code $channelCode
     */
    public function createForPage(Page $page, string $channelCode): Homepage;
}
