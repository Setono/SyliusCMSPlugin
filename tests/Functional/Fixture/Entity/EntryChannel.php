<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Fixture\Entity;

use Doctrine\ORM\Mapping as ORM;
use Setono\SyliusCMSPlugin\Model\EntryChannel as BaseEntryChannel;

/**
 * A project's own EntryChannel model. OverriddenRowModelsKernel overrides the entry_channel resource with it, mapped
 * to the shipped model's table, as Sylius has a project map an overridden model
 */
#[ORM\Entity]
#[ORM\Table(name: 'setono_sylius_cms__entry_channel')]
class EntryChannel extends BaseEntryChannel
{
}
