<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Targeting;

use Setono\SyliusCMSPlugin\Field\RecordChoicesProviderInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The store's locales, by code, with their names in the locale asked for, for a Choice field such as the locale
 * condition's locales. They're read once per request
 */
final class LocaleChoicesProvider implements RecordChoicesProviderInterface, ResetInterface
{
    /** @var array<LocaleInterface>|null */
    private ?array $locales = null;

    /**
     * @param RepositoryInterface<LocaleInterface> $localeRepository
     */
    public function __construct(private readonly RepositoryInterface $localeRepository)
    {
    }

    public function getChoices(string $localeCode): array
    {
        $this->locales ??= $this->localeRepository->findBy([], ['code' => 'ASC']);

        $choices = [];
        foreach ($this->locales as $locale) {
            $code = $locale->getCode();
            if (null !== $code) {
                $choices[$code] = $locale->getName($localeCode) ?? $code;
            }
        }

        return $choices;
    }

    public function reset(): void
    {
        $this->locales = null;
    }
}
