<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Field;

/**
 * The choices of a Choice field whose choices come from the store, such as the channels, which the field names in
 * `choicesProvider:`. A provider is a service, registered by autoconfiguration, whose id is its class.
 * See "Shipped field types" in docs/spec/extending.md
 */
interface ChoicesProviderInterface
{
    /**
     * @return array<string, string> value => label, in the locale
     */
    public function getChoices(string $localeCode): array;
}
