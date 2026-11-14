<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Publishing;

use Setono\SyliusCMSPlugin\Model\EntryInterface;

interface EntryPublisherInterface
{
    /**
     * Makes the entry's content the content shoppers see
     */
    public function publish(EntryInterface $entry): void;
}
