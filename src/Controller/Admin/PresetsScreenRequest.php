<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin;

use Setono\SyliusCMSPlugin\Preset\Preset;
use Setono\SyliusCMSPlugin\Preset\PresetProviderInterface;
use Setono\SyliusCMSPlugin\Security\AdminAccessChecker;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

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
        private CsrfTokenManagerInterface $csrfTokenManager,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * Changes the editor preset the URL names, and goes back to the Presets screen with the flash message the change
     * returns
     *
     * @param callable(Preset): array{'success'|'error', string, bool} $change returns the flash message's type, the
     *        message, a key of the flashes domain or a text, and whether the preset is still there to go back to
     *
     * @throws AccessDeniedException for anyone but an admin user
     * @throws NotFoundHttpException when there's no such editor preset
     * @throws AccessDeniedHttpException when the form's CSRF token isn't the screen's
     */
    public function submit(Request $request, string $uuid, callable $change): RedirectResponse
    {
        $this->adminAccessChecker->denyUnlessAdmin();

        $preset = $this->presets->find($uuid);
        if (null === $preset || $preset->isFromCode()) {
            throw new NotFoundHttpException(sprintf('There is no editor preset %s', $uuid));
        }

        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(self::CSRF_TOKEN_ID, (string) $request->request->get('_csrf_token')))) {
            throw new AccessDeniedHttpException('Invalid CSRF token');
        }

        [$type, $message, $kept] = $change($preset);

        $session = $request->getSession();
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add($type, $message);
        }

        $parameters = ['tab' => null === $preset->startingPoint ? PresetsAction::TAB_BLOCK_PRESETS : PresetsAction::TAB_STARTING_POINTS];
        if ($kept) {
            $parameters['_fragment'] = 'cms-preset-' . $preset->id();
        }

        return new RedirectResponse($this->urlGenerator->generate('setono_sylius_cms_admin_preset_index', $parameters));
    }
}
