<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Registry;

final class UnknownSlotException extends \InvalidArgumentException
{
    public function __construct(string $identifier)
    {
        parent::__construct(sprintf('There is no slot "%s". Declare it under setono_sylius_cms.slots', $identifier));
    }
}
