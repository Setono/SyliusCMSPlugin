<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Targeting;

use Setono\SyliusCMSPlugin\Field\ChoicesProviderInterface;
use Sylius\Component\Customer\Model\CustomerGroupInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Contracts\Service\ResetInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * What a customer group condition picks from: guests and customers signed in without a group, then the store's
 * customer groups, by code, with their names. The groups are read once per request.
 * See "Reserved customer group values" in docs/spec/content-model.md
 */
final class CustomerGroupChoicesProvider implements ChoicesProviderInterface, ResetInterface
{
    /** @var array<array-key, string>|null */
    private ?array $groups = null;

    /**
     * @param RepositoryInterface<CustomerGroupInterface> $customerGroupRepository
     */
    public function __construct(
        private readonly RepositoryInterface $customerGroupRepository,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getChoices(string $localeCode): array
    {
        if (null === $this->groups) {
            $this->groups = [];
            foreach ($this->customerGroupRepository->findBy([], ['name' => 'ASC']) as $group) {
                $code = (string) $group->getCode();
                $this->groups[$code] = $group->getName() ?? $code;
            }
        }

        return [
            CustomerGroupValue::GUEST => $this->translator->trans('setono_sylius_cms.ui.audience.guests', locale: $localeCode),
            CustomerGroupValue::NO_GROUP => $this->translator->trans('setono_sylius_cms.ui.audience.no_group', locale: $localeCode),
        ] + $this->groups;
    }

    public function reset(): void
    {
        $this->groups = null;
    }
}
