<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin\Api;

use Setono\SyliusCMSPlugin\Preview\Preview;
use Setono\SyliusCMSPlugin\Preview\PreviewContext;
use Setono\SyliusCMSPlugin\Preview\PreviewTokenSigner;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyProviderInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Routing\EntryPath;
use Setono\SyliusCMSPlugin\Security\AdminAccessChecker;
use Setono\SyliusCMSPlugin\Security\BuilderCsrfToken;
use Sylius\Component\Channel\Model\ChannelInterface as BaseChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Creates a preview token for an entry, a channel and a locale, and answers with the URL the builder loads in its
 * preview iframe: the working copy's URL, on the admin's own host. Shown to, Date, the sample object and the placement
 * join it with their tickets. See "Preview mode" in docs/spec/builder.md
 */
final readonly class PreviewTokenAction
{
    /**
     * @param ChannelRepositoryInterface<BaseChannelInterface> $channelRepository
     */
    public function __construct(
        private AdminAccessChecker $adminAccessChecker,
        private BuilderCsrfToken $csrfToken,
        private EntryRepositoryInterface $entryRepository,
        private ChannelRepositoryInterface $channelRepository,
        private WorkingCopyProviderInterface $workingCopyProvider,
        private ContentTypeRegistryInterface $contentTypes,
        private PreviewTokenSigner $signer,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $this->adminAccessChecker->denyUnlessAdmin();

        if (!$this->csrfToken->isValid($request)) {
            return new JsonResponse(['error' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
        }

        try {
            $body = $request->toArray();
        } catch (JsonException $e) {
            return self::badRequest($e->getMessage());
        }

        $entryUuid = $body['entry'] ?? null;
        $channelCode = $body['channel'] ?? null;
        $localeCode = $body['locale'] ?? null;
        if (!is_string($entryUuid) || !is_string($channelCode) || !is_string($localeCode)) {
            return self::badRequest('The body needs the entry, channel and locale, each as a string');
        }

        $entry = $this->entryRepository->findOneByUuid($entryUuid) ?? throw new NotFoundHttpException(sprintf('There is no entry %s', $entryUuid));

        // Entries are in every channel until channels arrive with the on-save settings
        $channel = $this->channelRepository->findOneByCode($channelCode);
        if (!$channel instanceof ChannelInterface) {
            return self::badRequest(sprintf('There is no channel %s', $channelCode));
        }

        if (!$channel->getLocales()->exists(static fn (int|string $key, LocaleInterface $locale): bool => $locale->getCode() === $localeCode)) {
            return self::badRequest(sprintf('The channel %s has no locale %s', $channelCode, $localeCode));
        }

        $workingCopy = $this->workingCopyProvider->getWorkingCopy($entry);
        $path = EntryPath::of($this->contentTypes->getByEntry($workingCopy), $workingCopy, $localeCode);
        if (null === $path) {
            return self::badRequest(sprintf('The entry has no URL in %s', $localeCode));
        }

        return new JsonResponse(['url' => $this->urlGenerator->generate('setono_sylius_cms_shop_entry', [
            '_locale' => $localeCode,
            'path' => $path,
            PreviewContext::QUERY_PARAMETER => $this->signer->sign(new Preview($entry->getUuid(), $channelCode, $localeCode)),
        ], UrlGeneratorInterface::ABSOLUTE_URL)]);
    }

    private static function badRequest(string $error): JsonResponse
    {
        return new JsonResponse(['error' => $error], Response::HTTP_BAD_REQUEST);
    }
}
