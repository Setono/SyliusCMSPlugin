<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Targeting;

use Setono\SyliusCMSPlugin\Field\ChoicesProviderInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The store's locales, by code, with their names in the locale asked for, for a Choice field such as the locale
 * condition's locales. They're read once per request
 */
final class LocaleChoicesProvider implements ChoicesProviderInterface, ResetInterface
{
    /** @var list<LocaleInterface>|null */
    private ?array $locales = null;

    /**
     * @param RepositoryInterface<LocaleInterface> $localeRepository
     */
    public function __construct(private readonly RepositoryInterface $localeRepository)
    {
    }

    public function getChoices(string $localeCode): array
    {
        $this->locales ??= array_values($this->localeRepository->findBy([], ['code' => 'ASC']));

        $choices = [];
        foreach ($this->locales as $locale) {
            $code = (string) $locale->getCode();
            $choices[$code] = $locale->getName($localeCode) ?? $code;
        }

        return $choices;
    }

    public function reset(): void
    {
        $this->locales = null;
    }
}
