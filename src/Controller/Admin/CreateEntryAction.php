<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin;

use Doctrine\Persistence\ManagerRegistry;
use Psr\Container\ContainerInterface;
use Setono\SyliusCMSPlugin\Admin\NewEntryLocaleResolverInterface;
use Setono\SyliusCMSPlugin\Event\EntryCreated;
use Setono\SyliusCMSPlugin\Form\Type\CreateEntryType;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Publishing\EditRecorder;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistryInterface;
use Setono\SyliusCMSPlugin\Security\AdminAccessChecker;
use Setono\SyliusCMSPlugin\Targeting\ChannelsAndAudienceModifierInterface;
use Setono\SyliusCMSPlugin\Tree\StoredLayout;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Setono\SyliusCMSPlugin\Validation\EntryUrlValidatorInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Twig\Environment;

/**
 * The create dialog's end on the server, for a content type. GET answers the dialog's form: alone when the dialog
 * loads it, and as a page otherwise. POST creates a never-published entry through the content type's resource factory,
 * which starts it in every channel and in the default layout, puts it in the layout and the channels the editor picked,
 * and opens the builder, whose back
 * button goes to the entry's overview page. A submit the form refuses answers the form with its errors, as a page, and so
 * does a slug whose URL a route or another entry's published URL has, as saving refuses one, with the error on the slug.
 * See "Create dialog" in docs/spec/admin.md and "Reserved URLs" in docs/spec/urls-and-seo.md
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
        private ChannelsAndAudienceModifierInterface $channelsAndAudienceModifier,
        private NewEntryLocaleResolverInterface $localeResolver,
        private FormFactoryInterface $formFactory,
        private UrlGeneratorInterface $urlGenerator,
        private Environment $twig,
        private EventDispatcherInterface $eventDispatcher,
        private EditRecorder $editRecorder,
        private LayoutRegistryInterface $layouts,
        private EntryUrlValidatorInterface $urlValidator,
    ) {
    }

    public function __invoke(Request $request, string $contentType): Response
    {
        $this->adminAccessChecker->denyUnlessAdmin();

        if (!$this->contentTypes->has($contentType) || !$this->entryFactories->has($contentType)) {
            throw new NotFoundHttpException(sprintf('There is no content type "%s"', $contentType));
        }

        $metadata = $this->contentTypes->get($contentType);

        // The dialog shows the form on another page, so it posts here
        $form = $this->formFactory->create(CreateEntryType::class, null, [
            'slug' => $metadata->hasOwnUrl(),
            'layouts' => $this->layoutChoices($contentType),
            'action' => $this->urlGenerator->generate('setono_sylius_cms_admin_entry_create', ['contentType' => $contentType]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{title: string, slug?: string, layout?: string, channels?: array<int, string>} $data */
            $data = $form->getData();

            $entry = $this->createEntry($contentType);
            if (isset($data['channels'])) {
                $this->channelsAndAudienceModifier->setChannels($entry, $data['channels']);
            }

            if (isset($data['layout'])) {
                $layout = $this->layouts->get($contentType, $data['layout']);
                $entry->setBlockTree(new StoredTree(new StoredLayout($layout->code, $layout->version), $entry->getBlockTree()->blocks));
            }

            // The title and slug go into the admin user's locale, or else the channels' default
            $localeCode = $this->localeResolver->resolve($entry);
            $entry->setCurrentLocale($localeCode);
            $entry->setFallbackLocale($localeCode);
            $entry->setTitle($data['title']);
            $entry->setSlug($data['slug'] ?? null);

            if (!$this->hasUrlErrors($form, $entry)) {
                return $this->save($entry, $contentType);
            }
        }

        $template = !$form->isSubmitted() && $request->isXmlHttpRequest() ? '_create_form' : 'create';

        return new Response($this->twig->render(sprintf('@SetonoSyliusCMSPlugin/admin/entry/%s.html.twig', $template), [
            'form' => $form->createView(),
            'content_type' => $metadata,
        ]), $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK);
    }

    /**
     * Adds the entry's URL errors to the slug field: a URL a route takes, or another entry has published. The form
     * checked the slug's rules already
     *
     * @param FormInterface<array{title: string, slug?: string, layout?: string, channels?: array<int, string>}|null> $form
     */
    private function hasUrlErrors(FormInterface $form, EntryInterface $entry): bool
    {
        if (!$form->has('slug')) {
            return false;
        }

        $errors = $this->urlValidator->validate($entry);
        foreach ($errors as $error) {
            $form->get('slug')->addError(new FormError($error->message));
        }

        return [] !== $errors;
    }

    private function save(EntryInterface $entry, string $contentType): Response
    {
        $this->editRecorder->record($entry);

        $manager = $this->managerRegistry->getManagerForClass($entry::class) ?? throw new \LogicException(sprintf('No Doctrine manager manages %s', $entry::class));
        $manager->persist($entry);
        $manager->flush();

        // After the flush's transaction commits
        $this->eventDispatcher->dispatch(new EntryCreated($entry->getUuid(), $contentType));

        return new RedirectResponse($this->urlGenerator->generate('setono_sylius_cms_admin_builder', ['uuid' => $entry->getUuid()]));
    }

    /**
     * The layouts editors can pick for the content type, the default first, by code, with their labels and thumbnails
     *
     * @return array<string, array{label: string, thumbnail: string|null}>
     */
    private function layoutChoices(string $contentType): array
    {
        $choices = [];
        foreach ($this->layouts->all($contentType) as $layout) {
            if (!$layout->disabled) {
                $choices[$layout->code] = ['label' => $layout->label, 'thumbnail' => $layout->thumbnail];
            }
        }

        return $choices;
    }

    /**
     * A new entry of the content type, from its resource factory, which starts it in every channel and in the default layout
     */
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

        return $entry;
    }
}
