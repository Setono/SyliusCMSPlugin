<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Twig;

use Setono\SyliusCMSPlugin\Registry\ContentTypeMetadata;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\UnknownContentTypeException;
use Sylius\Component\Customer\Model\CustomerGroupInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Contracts\Service\ResetInterface;
use Twig\Extension\RuntimeExtensionInterface;

/**
 * @internal
 */
final class AdminRuntime implements RuntimeExtensionInterface, ResetInterface
{
    /** @var array<string, string>|null */
    private ?array $customerGroupNames = null;

    /**
     * @param RepositoryInterface<CustomerGroupInterface> $customerGroupRepository
     */
    public function __construct(
        private readonly ContentTypeRegistryInterface $contentTypes,
        private readonly RepositoryInterface $customerGroupRepository,
    ) {
    }

    /**
     * @throws UnknownContentTypeException
     */
    public function contentType(string $code): ContentTypeMetadata
    {
        return $this->contentTypes->get($code);
    }

    /**
     * The names of the store's customer groups by code, which audience labels show. Read once per request
     *
     * @return array<string, string>
     */
    public function customerGroupNames(): array
    {
        if (null === $this->customerGroupNames) {
            $this->customerGroupNames = [];
            foreach ($this->customerGroupRepository->findAll() as $group) {
                $code = $group->getCode();
                if (null !== $code) {
                    $this->customerGroupNames[$code] = $group->getName() ?? $code;
                }
            }
        }

        return $this->customerGroupNames;
    }

    public function reset(): void
    {
        $this->customerGroupNames = null;
    }
}
