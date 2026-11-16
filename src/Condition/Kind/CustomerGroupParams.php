<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Condition\Kind;

use Setono\SyliusCMSPlugin\Field\Choice;
use Setono\SyliusCMSPlugin\Targeting\CustomerGroupChoicesProvider;

final class CustomerGroupParams
{
    /** @var list<string> customer group codes, "@guest" and "@no_group". A deleted group's code is left out */
    #[Choice(label: 'setono_sylius_cms.field.customer_group_condition.groups', multiple: true, choicesProvider: CustomerGroupChoicesProvider::class)]
    public array $groups = [];
}
