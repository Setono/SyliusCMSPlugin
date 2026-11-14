<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin;

use Doctrine\Persistence\ManagerRegistry;
use Psr\Container\ContainerInterface;
use Setono\SyliusCMSPlugin\Form\Type\CreateEntryType;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Security\AdminAccessChecker;
use Sylius\Resource\Factory\FactoryInterface;
use Sylius\Resource\Translation\Provider\TranslationLocaleProviderInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * Creates an entry of a content type from a title and a slug, then opens the builder
 */
final readonly class CreateEntryAction
{
    /**
     * @param ContainerInterface $entryFactories the content types' resource factories, keyed by content type code
     */
    public function __construct(
        private AdminAccessChecker $adminAccessChecker,
        private ContentTypeRegistryInterface $contentTypes,
        private ContainerInterface $entryFactories,
        private ManagerRegistry $managerRegistry,
        private TranslationLocaleProviderInterface $translationLocaleProvider,
        private FormFactoryInterface $formFactory,
        private UrlGeneratorInterface $urlGenerator,
        private Environment $twig,
    ) {
    }

    public function __invoke(Request $request, string $contentType): Response
    {
        $this->adminAccessChecker->denyUnlessAdmin();

        if (!$this->contentTypes->has($contentType) || !$this->entryFactories->has($contentType)) {
            throw new NotFoundHttpException(sprintf('There is no content type "%s"', $contentType));
        }

        $metadata = $this->contentTypes->get($contentType);

        $form = $this->formFactory->create(CreateEntryType::class, null, ['slug' => $metadata->hasOwnUrl()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{title: string, slug?: string} $data */
            $data = $form->getData();

            $entry = $this->createEntry($contentType);
            $entry->setTitle($data['title']);
            $entry->setSlug($data['slug'] ?? null);

            $manager = $this->managerRegistry->getManagerForClass($entry::class) ?? throw new \LogicException(sprintf('No Doctrine manager manages %s', $entry::class));
            $manager->persist($entry);
            $manager->flush();

            return new RedirectResponse($this->urlGenerator->generate('setono_sylius_cms_admin_builder', ['uuid' => $entry->getUuid()]));
        }

        return new Response($this->twig->render('@SetonoSyliusCMSPlugin/admin/entry/create.html.twig', [
            'form' => $form->createView(),
            'content_type' => $metadata,
        ]), $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK);
    }

    private function createEntry(string $contentType): EntryInterface
    {
        $factory = $this->entryFactories->get($contentType);
        if (!$factory instanceof FactoryInterface) {
            throw new \LogicException(sprintf('The factory of the content type "%s" must implement %s', $contentType, FactoryInterface::class));
        }

        $entry = $factory->createNew();
        if (!$entry instanceof EntryInterface) {
            throw new \LogicException(sprintf('The factory of the content type "%s" must create %s', $contentType, EntryInterface::class));
        }

        // The entry starts with a translation in the store's default locale. Adding translations comes with #232
        $localeCode = $this->translationLocaleProvider->getDefaultLocaleCode();
        $entry->setCurrentLocale($localeCode);
        $entry->setFallbackLocale($localeCode);

        return $entry;
    }
}
