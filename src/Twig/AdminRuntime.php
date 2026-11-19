<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Twig;

use Setono\SyliusCMSPlugin\Admin\EntryRow;
use Setono\SyliusCMSPlugin\Admin\EntryRows;
use Setono\SyliusCMSPlugin\Admin\TranslationChip;
use Setono\SyliusCMSPlugin\Admin\TranslationChips;
use Setono\SyliusCMSPlugin\Collection\CollectionMetadata;
use Setono\SyliusCMSPlugin\Collection\CollectionNames;
use Setono\SyliusCMSPlugin\Collection\Collections;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeMetadata;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\UnknownContentTypeException;
use Sylius\Component\Customer\Model\CustomerGroupInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Contracts\Service\ResetInterface;
use Twig\Extension\RuntimeExtensionInterface;

/**
 * The cms_admin_* functions of the entry lists, their rows and the create dialog
 *
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
        private readonly EntryRows $entryRows,
        private readonly TranslationChips $translationChips,
        private readonly Collections $collections,
        private readonly CollectionNames $collectionNames,
    ) {
    }

    /**
     * The entry's row in the lists, as it was loaded with its page, or else loaded alone
     */
    public function entryRow(EntryInterface $entry): EntryRow
    {
        return $this->entryRows->get($entry);
    }

    /**
     * @return list<TranslationChip>
     */
    public function translationChips(EntryRow $row): array
    {
        return $this->translationChips->of($row);
    }

    /**
     * @throws UnknownContentTypeException
     */
    public function contentType(string $code): ContentTypeMetadata
    {
        return $this->contentTypes->get($code);
    }

    /**
     * Every content type that isn't switched off, which the create dialog offers to pick from
     *
     * @return list<ContentTypeMetadata>
     */
    public function contentTypes(): array
    {
        return $this->contentTypes->enabled();
    }

    /**
     * The collection with categories whose entries or categories are of the content type, such as the blog for blog
     * posts and blog categories, whose lists switch between its entries, its categories and both. Null for any other
     */
    public function collection(string $contentType): ?CollectionMetadata
    {
        $collection = $this->collections->of($contentType);

        return null !== $collection && $collection->hasCategories() ? $collection : null;
    }

    /**
     * The collection's name, which its hub item and its lists show, such as Blog
     */
    public function collectionName(CollectionMetadata $collection): string
    {
        return $this->collectionNames->collection($collection);
    }

    /**
     * The content type's plural, which names its list, such as Posts
     */
    public function contentTypePlural(ContentTypeMetadata $contentType): string
    {
        return $this->collectionNames->plural($contentType);
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
