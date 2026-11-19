<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Collection;

use Setono\SyliusCMSPlugin\Field\ChoicesProviderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * What the Entries block's display is picked from: every display a content type has a card for, such as grid,
 * carousel and accordion, named by setono_sylius_cms.choice.entries_display.<display>, or by the display itself without
 * a translation. The builder narrows them to the displays the listed entries have cards for. See "The Entries block" in
 * docs/spec/collections.md
 */
final readonly class CardDisplayChoicesProvider implements ChoicesProviderInterface
{
    public function __construct(
        private CardDisplays $cardDisplays,
        private TranslatorInterface $translator,
    ) {
    }

    public function getChoices(string $localeCode): array
    {
        $choices = [];
        foreach ($this->cardDisplays->all() as $display) {
            $key = sprintf('setono_sylius_cms.choice.entries_display.%s', $display);
            $label = $this->translator->trans($key, [], null, $localeCode);
            $choices[$display] = $label === $key ? $display : $label;
        }

        return $choices;
    }
}
