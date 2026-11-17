<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Builder;

use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The UI strings the builder app shows, translated in the current locale, which in the admin is the admin user's locale:
 * every message whose key starts with a prefix, from the locale's catalogue and its fallbacks
 */
final readonly class UiStrings
{
    public function __construct(
        private TranslatorInterface $translator,
        private TranslatorBagInterface $catalogues,
    ) {
    }

    /**
     * @return array<string, string> by key, sorted
     */
    public function withPrefix(string $prefix): array
    {
        $strings = [];
        for ($catalogue = $this->catalogues->getCatalogue(); null !== $catalogue; $catalogue = $catalogue->getFallbackCatalogue()) {
            foreach (array_keys($catalogue->all('messages')) as $id) {
                if (is_string($id) && str_starts_with($id, $prefix)) {
                    $strings[$id] ??= $this->translator->trans($id);
                }
            }
        }

        ksort($strings);

        return $strings;
    }
}
