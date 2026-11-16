<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin;

use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;
use Setono\SyliusCMSPlugin\Admin\EntryListCriteria;
use Setono\SyliusCMSPlugin\Admin\EntryListQueryInterface;
use Setono\SyliusCMSPlugin\Admin\EntryRows;
use Setono\SyliusCMSPlugin\Form\Type\EntryListFilterType;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Security\AdminAccessChecker;
use Sylius\Component\Customer\Model\CustomerGroupInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;

/**
 * All entries, and each content type's list of entries, filtered by search, status and channel.
 * See "Entry lists" in docs/spec/admin.md
 */
final readonly class EntryIndexAction
{
    public const PER_PAGE = 25;

    /**
     * @param RepositoryInterface<CustomerGroupInterface> $customerGroupRepository
     */
    public function __construct(
        private AdminAccessChecker $adminAccessChecker,
        private ContentTypeRegistryInterface $contentTypes,
        private EntryListQueryInterface $entryListQuery,
        private EntryRows $entryRows,
        private FormFactoryInterface $formFactory,
        private LocaleContextInterface $localeContext,
        private RepositoryInterface $customerGroupRepository,
        private Environment $twig,
    ) {
    }

    /**
     * @param string|null $contentType the code of the content type whose list it is, or null for All entries
     */
    public function __invoke(Request $request, ?string $contentType = null): Response
    {
        $this->adminAccessChecker->denyUnlessAdmin();

        if (null !== $contentType && !$this->contentTypes->has($contentType)) {
            throw new NotFoundHttpException(sprintf('There is no content type "%s"', $contentType));
        }

        // Filters it doesn't offer are left out, as Sylius's grids leave them out
        $filters = $this->formFactory->createNamed('criteria', EntryListFilterType::class);
        $filters->handleRequest($request);
        $criteria = $filters->isSubmitted() && $filters->isValid() ? $filters->getData() : null;

        /** @var Pagerfanta<EntryInterface> $entries */
        $entries = new Pagerfanta(new QueryAdapter($this->entryListQuery->createQueryBuilder($contentType, $criteria instanceof EntryListCriteria ? $criteria : new EntryListCriteria())));
        $entries->setNormalizeOutOfRangePages(true);
        $entries->setMaxPerPage(self::PER_PAGE);
        $entries->setCurrentPage(max(1, (int) $request->query->get('page', '1')));

        return new Response($this->twig->render('@SetonoSyliusCMSPlugin/admin/entry/index.html.twig', [
            'content_type' => null === $contentType ? null : $this->contentTypes->get($contentType),
            'content_types' => $this->contentTypes->all(),
            'filters' => $filters->createView(),
            'entries' => $entries,
            'rows' => $this->entryRows->build($entries->getCurrentPageResults(), $this->localeContext->getLocaleCode()),
            'customer_group_names' => $this->customerGroupNames(),
        ]));
    }

    /**
     * The names of the store's customer groups, which audience labels show, by code
     *
     * @return array<string, string>
     */
    private function customerGroupNames(): array
    {
        $names = [];
        foreach ($this->customerGroupRepository->findAll() as $group) {
            $names[(string) $group->getCode()] = (string) $group->getName();
        }

        return $names;
    }
}
