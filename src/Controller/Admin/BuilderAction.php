<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Security\AdminAccessChecker;
use Setono\SyliusCMSPlugin\Security\BuilderCsrfToken;
use Sylius\Resource\Translation\Provider\TranslationLocaleProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * The builder's full-screen page, which loads the prebuilt builder app with its config. See docs/spec/builder.md
 */
final readonly class BuilderAction
{
    public function __construct(
        private AdminAccessChecker $adminAccessChecker,
        private EntryRepositoryInterface $entryRepository,
        private TranslationLocaleProviderInterface $translationLocaleProvider,
        private BuilderCsrfToken $csrfToken,
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
        private Environment $twig,
    ) {
    }

    public function __invoke(Request $request, string $uuid): Response
    {
        $this->adminAccessChecker->denyUnlessAdmin();

        $entry = $this->entryRepository->findOneByUuid($uuid) ?? throw new NotFoundHttpException(sprintf('There is no entry %s', $uuid));

        return new Response($this->twig->render('@SetonoSyliusCMSPlugin/admin/builder.html.twig', [
            'entry' => $entry,
            'config' => [
                'entryUuid' => $entry->getUuid(),
                'locale' => $this->editedLocale($entry),
                'csrfToken' => $this->csrfToken->value(),
                'backUrl' => $this->backUrl($request),
                'endpoints' => [
                    'registry' => $this->urlGenerator->generate('setono_sylius_cms_admin_api_registry'),
                    'entry' => $this->urlGenerator->generate('setono_sylius_cms_admin_api_entry_show', ['uuid' => $entry->getUuid()]),
                    'publish' => $this->urlGenerator->generate('setono_sylius_cms_admin_api_entry_publish', ['uuid' => $entry->getUuid()]),
                ],
                // The rest of the UI strings arrive with the registry, so the app shows these until it has loaded
                'strings' => [
                    'setono_sylius_cms.builder.loading' => $this->translator->trans('setono_sylius_cms.builder.loading'),
                    'setono_sylius_cms.builder.load_failed' => $this->translator->trans('setono_sylius_cms.builder.load_failed'),
                ],
            ],
        ]));
    }

    /**
     * The builder edits one locale at a time: the store's default locale, or else the entry's first translation.
     * The locale switcher comes with translations (#232)
     */
    private function editedLocale(EntryInterface $entry): string
    {
        $default = $this->translationLocaleProvider->getDefaultLocaleCode();
        $locales = $entry->getTranslations()->getKeys();

        return in_array($default, $locales, true) || [] === $locales ? $default : (string) $locales[0];
    }

    /**
     * Back returns to where the builder was opened from, when that's a path on this host, and otherwise to the dashboard
     */
    private function backUrl(Request $request): string
    {
        $back = $request->query->get('back');
        if (is_string($back) && 1 === preg_match('#^/(?![/\\\\])#', $back)) {
            return $back;
        }

        return $this->urlGenerator->generate('sylius_admin_dashboard');
    }
}
