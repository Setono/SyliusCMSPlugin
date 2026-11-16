<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Condition\Kind;

use Setono\SyliusCMSPlugin\Field\Choice;
use Setono\SyliusCMSPlugin\Targeting\LocaleChoicesProvider;

final class LocaleParams
{
    /** @var list<string> locale codes */
    #[Choice(label: 'setono_sylius_cms.field.locale_condition.locales', multiple: true, choicesProvider: LocaleChoicesProvider::class)]
    public array $locales = [];
}
