<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin;

use Doctrine\Persistence\ManagerRegistry;
use Psr\Container\ContainerInterface;
use Setono\SyliusCMSPlugin\Admin\NewEntryLocaleResolverInterface;
use Setono\SyliusCMSPlugin\Admin\StartingPointChoice;
use Setono\SyliusCMSPlugin\Admin\StartingPointChoices;
use Setono\SyliusCMSPlugin\Event\EntryCreated;
use Setono\SyliusCMSPlugin\Form\Type\CreateEntryType;
use Setono\SyliusCMSPlugin\Localization\ChannelLocales;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Preset\StartingPointTransformer;
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
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * The create dialog's end on the server, for a content type. GET answers the dialog's form: alone when the dialog
 * loads it, and as a page otherwise. POST creates a never-published entry through the content type's resource factory,
 * which starts it in every channel and in the default layout, puts it in the channels the editor picked, gives it the
 * tree of the starting point picked, its blocks and its layout, or else the layout picked, and opens the builder, whose
 * back button goes to the entry's overview page. A submit the form refuses answers the form with its errors, as a page,
 * and so does a slug whose URL a route or another entry's published URL has, as saving refuses one, with the error on
 * the slug. See "Create dialog" in docs/spec/admin.md, "Starting points" in docs/spec/layouts-and-presets.md and
 * "Reserved URLs" in docs/spec/urls-and-seo.md
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
        private StartingPointChoices $startingPoints,
        private StartingPointTransformer $startingPointTransformer,
        private ChannelLocales $channelLocales,
        private TranslatorInterface $translator,
    ) {
    }

    public function __invoke(Request $request, string $contentType): Response
    {
        $this->adminAccessChecker->denyUnlessAdmin();

        // A content type switched off in setono_sylius_cms.disabled.content_types has no entries to create
        if (!$this->contentTypes->isEnabled($contentType) || !$this->entryFactories->has($contentType)) {
            throw new NotFoundHttpException(sprintf('There is no content type "%s"', $contentType));
        }

        $metadata = $this->contentTypes->get($contentType);
        // A content type without a block tree has no starting points
        $startingPoints = null === $metadata->blocks ? [] : $this->startingPoints->of($contentType);

        // The dialog shows the form on another page, so it posts here
        $form = $this->formFactory->create(CreateEntryType::class, null, [
            'slug' => $metadata->hasOwnUrl(),
            'layouts' => $this->layoutChoices($contentType),
            'starting_points' => $startingPoints,
            'action' => $this->urlGenerator->generate('setono_sylius_cms_admin_entry_create', ['contentType' => $contentType]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{title: string, slug?: string, layout?: string, startingPoint?: string|null, channels?: array<int, string>} $data */
            $data = $form->getData();

            $entry = $this->createEntry($contentType);
            if (isset($data['channels'])) {
                $this->channelsAndAudienceModifier->setChannels($entry, $data['channels']);
            }

            // The title and slug go into the admin user's locale, or else the channels' default
            $localeCode = $this->localeResolver->resolve($entry);
            $entry->setCurrentLocale($localeCode);
            $entry->setFallbackLocale($localeCode);
            $entry->setTitle($data['title']);
            $entry->setSlug($data['slug'] ?? null);

            $startingPoint = self::picked($startingPoints, $data['startingPoint'] ?? null);
            if (null !== $startingPoint) {
                // The starting point brings its own layout, so the one picked for Blank is left out
                $this->start($request, $entry, $startingPoint, $localeCode);
            } elseif (isset($data['layout'])) {
                $layout = $this->layouts->get($contentType, $data['layout']);
                $entry->setBlockTree(new StoredTree(new StoredLayout($layout->code, $layout->version), $entry->getBlockTree()->blocks));
            }

            if (!$this->hasUrlErrors($form, $entry)) {
                return $this->save($entry, $contentType);
            }
        }

        $template = !$form->isSubmitted() && $request->isXmlHttpRequest() ? '_create_form' : 'create';

        return new Response($this->twig->render(sprintf('@SetonoSyliusCMSPlugin/admin/entry/%s.html.twig', $template), [
            'form' => $form->createView(),
            'content_type' => $metadata,
            'starting_points' => $startingPoints,
        ]), $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK);
    }

    /**
     * Gives the new entry the starting point's tree: its blocks and its layout, with the values of the locales of the
     * entry's channels and the one its title is in. What it left out, as inserting does, such as blocks of a disabled
     * type, the builder tells the editor once it opens (BuilderAction::NOTICES). A starting point the code is older than,
     * which the transformer would refuse, never gets here: StartingPointChoices disables it, from the same upgrade, and
     * the form refuses a disabled one
     */
    private function start(Request $request, EntryInterface $entry, StartingPointChoice $startingPoint, string $localeCode): void
    {
        $locales = array_values(array_unique([$localeCode, ...$this->channelLocales->ofChannels($entry->getChannelCodes())]));
        $started = $this->startingPointTransformer->transform($startingPoint->preset, $locales);
        $entry->setBlockTree($started->tree);

        $session = $request->hasSession() ? $request->getSession() : null;
        if ($session instanceof FlashBagAwareSessionInterface) {
            foreach ($started->notices as $notice) {
                $session->getFlashBag()->add(BuilderAction::NOTICES, $notice->trans($this->translator));
            }
        }
    }

    /**
     * @param list<StartingPointChoice> $startingPoints
     */
    private static function picked(array $startingPoints, ?string $id): ?StartingPointChoice
    {
        foreach ($startingPoints as $startingPoint) {
            if ($startingPoint->id() === $id) {
                return $startingPoint;
            }
        }

        return null;
    }

    /**
     * Adds the entry's URL errors to the slug field: a URL a route takes, or another entry has published. The form
     * checked the slug's rules already
     *
     * @param FormInterface<array{title: string, slug?: string, layout?: string, startingPoint?: string|null, channels?: array<int, string>}|null> $form
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
