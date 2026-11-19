<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin;

use Setono\SyliusCMSPlugin\Preset\Preset;
use Setono\SyliusCMSPlugin\Preset\PresetProviderInterface;
use Setono\SyliusCMSPlugin\Security\AdminAccessChecker;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * What the Presets screen's forms check before they change an editor preset: an admin user, the editor preset the URL
 * names, and the screen's CSRF token. And how they answer: back on the screen, in the preset's tab, with the outcome as
 * a flash message. Code presets are read-only, so they're never found here
 *
 * @internal
 */
final readonly class PresetsScreenRequest
{
    public const CSRF_TOKEN_ID = 'setono_sylius_cms_presets';

    public function __construct(
        private AdminAccessChecker $adminAccessChecker,
        private PresetProviderInterface $presets,
        private FormFactoryInterface $formFactory,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * The name of an editor preset's forms, which the screen has one of per row, so their fields' ids differ
     */
    public static function formName(string $uuid): string
    {
        return sprintf('cms_preset_%s', str_replace('-', '', strtolower($uuid)));
    }

    /**
     * An editor preset's Edit form (Form\Type\PresetDetailsType) or Delete form (Form\Type\DeletePresetType)
     *
     * @template TFormType of FormTypeInterface<TData>
     * @template TData
     *
     * @param class-string<TFormType> $type
     * @param TData|null $data
     *
     * @return FormInterface<TData|null>
     */
    public function form(string $type, Preset $preset, mixed $data = null, ?string $action = null): FormInterface
    {
        return $this->formFactory->createNamed(self::formName($preset->id()), $type, $data, null === $action ? [] : ['action' => $action]);
    }

    /**
     * Changes the editor preset the URL names with what its form of the type submits, and goes back to the Presets
     * screen with the flash message the change returns
     *
     * @template TData
     *
     * @param class-string<FormTypeInterface<TData>> $type
     * @param callable(Preset, TData|null): array{'success'|'error', string} $change given the preset and the form's data,
     *        returns the flash message's type and the message, a key of the flashes domain or a text
     *
     * @throws AccessDeniedException for anyone but an admin user
     * @throws NotFoundHttpException when there's no such editor preset
     * @throws AccessDeniedHttpException when the form isn't submitted with the screen's CSRF token
     */
    public function submit(Request $request, string $uuid, string $type, callable $change): RedirectResponse
    {
        $this->adminAccessChecker->denyUnlessAdmin();

        $preset = $this->presets->find($uuid);
        if (null === $preset || $preset->isFromCode()) {
            throw new NotFoundHttpException(sprintf('There is no editor preset %s', $uuid));
        }

        // The form reads the values, and PresetDetails checks them, so it's invalid only for its CSRF token
        $form = $this->form($type, $preset)->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            throw new AccessDeniedHttpException('Invalid CSRF token');
        }

        [$flash, $message] = $change($preset, $form->getData());

        $session = $request->getSession();
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add($flash, $message);
        }

        // Without a fragment: a browser takes a redirect to the page it's on with only a fragment more as a move on that page
        return new RedirectResponse($this->urlGenerator->generate('setono_sylius_cms_admin_preset_index', [
            'tab' => null === $preset->startingPoint ? PresetsAction::TAB_BLOCK_PRESETS : PresetsAction::TAB_STARTING_POINTS,
        ]));
    }
}
