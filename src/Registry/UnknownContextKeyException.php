<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Registry;

final class UnknownContextKeyException extends \InvalidArgumentException
{
    public function __construct(string $key)
    {
        parent::__construct(sprintf('There is no context key "%s". Declare it under setono_sylius_cms.context_keys', $key));
    }
}
