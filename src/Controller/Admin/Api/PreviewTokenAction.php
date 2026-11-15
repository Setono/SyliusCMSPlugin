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
use Sylius\Component\Channel\Model\ChannelInterface as BaseChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
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
        private BuilderRequest $builderRequest,
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
        return $this->builderRequest->withCsrfToken($request, fn (): JsonResponse => $this->createToken($request));
    }

    private function createToken(Request $request): JsonResponse
    {
        try {
            $body = $request->toArray();
        } catch (JsonException $e) {
            return BuilderRequest::badRequest($e->getMessage());
        }

        $entryUuid = $body['entry'] ?? null;
        $channelCode = $body['channel'] ?? null;
        $localeCode = $body['locale'] ?? null;
        if (!is_string($entryUuid) || !is_string($channelCode) || !is_string($localeCode)) {
            return BuilderRequest::badRequest('The body needs the entry, channel and locale, each as a string');
        }

        $entry = $this->entryRepository->findOneByUuid($entryUuid) ?? throw new NotFoundHttpException(sprintf('There is no entry %s', $entryUuid));

        $channel = $this->channelRepository->findOneByCode($channelCode);
        if (!$channel instanceof ChannelInterface) {
            return BuilderRequest::badRequest(sprintf('There is no channel %s', $channelCode));
        }

        if (!in_array($channelCode, $entry->getChannelCodes(), true)) {
            return BuilderRequest::badRequest(sprintf("The entry isn't in the channel %s", $channelCode));
        }

        if (!$channel->getLocales()->exists(static fn (int|string $key, LocaleInterface $locale): bool => $locale->getCode() === $localeCode)) {
            return BuilderRequest::badRequest(sprintf('The channel %s has no locale %s', $channelCode, $localeCode));
        }

        $workingCopy = $this->workingCopyProvider->getWorkingCopy($entry);
        $path = EntryPath::of($this->contentTypes->getByEntry($workingCopy), $workingCopy, $localeCode);
        if (null === $path) {
            return BuilderRequest::badRequest(sprintf('The entry has no URL in %s', $localeCode));
        }

        return new JsonResponse(['url' => $this->urlGenerator->generate('setono_sylius_cms_shop_entry', [
            '_locale' => $localeCode,
            'path' => $path,
            PreviewContext::QUERY_PARAMETER => $this->signer->sign(new Preview($entry->getUuid(), $channelCode, $localeCode)),
        ], UrlGeneratorInterface::ABSOLUTE_URL)]);
    }
}
