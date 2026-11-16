<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Builder\Search;

use Setono\SyliusCMSPlugin\Field\ChoicesProviderInterface;

/**
 * Searches the choices of a choices provider by name and by value, ignoring case, in the provider's order, such as the
 * customer groups with guests and customers without a group first
 *
 * @internal
 */
final readonly class ChoicesSearch implements SearchInterface
{
    /** The most results it answers */
    public const LIMIT = 20;

    public function __construct(private ChoicesProviderInterface $choicesProvider)
    {
    }

    public function search(string $query, string $localeCode): array
    {
        $needle = mb_strtolower(trim($query));

        $results = [];
        foreach ($this->choicesProvider->getChoices($localeCode) as $value => $label) {
            $value = (string) $value;
            if (!str_contains(mb_strtolower($label), $needle) && !str_contains(mb_strtolower($value), $needle)) {
                continue;
            }

            $results[] = new SearchResult($value, $label);
            if (self::LIMIT === count($results)) {
                break;
            }
        }

        return $results;
    }
}
