<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin\Api;

use Setono\SyliusCMSPlugin\Controller\Shop\SnippetPreviewAction;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Preview\Preview;
use Setono\SyliusCMSPlugin\Preview\PreviewContext;
use Setono\SyliusCMSPlugin\Preview\PreviewTokenSigner;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyProviderInterface;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Seo\CanonicalUrlGeneratorInterface;
use Setono\SyliusCMSPlugin\Targeting\CustomerGroupValue;
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
 * Creates a preview token for an entry, a channel, a locale, Shown to and Date, and answers with the URL the builder
 * loads in its preview iframe: the working copy's canonical URL, on the admin's own host, which is the channel's root for
 * its homepage, and for a snippet a plain shop page. The sample object and the placement join it with their tickets. See "Preview mode" in docs/spec/builder.md
 */
final readonly class PreviewTokenAction
{
    private const DATE_FORMAT = 'Y-m-d\TH:i:s\Z';

    /**
     * @param ChannelRepositoryInterface<BaseChannelInterface> $channelRepository
     */
    public function __construct(
        private BuilderRequest $builderRequest,
        private EntryRepositoryInterface $entryRepository,
        private ChannelRepositoryInterface $channelRepository,
        private WorkingCopyProviderInterface $workingCopyProvider,
        private PreviewTokenSigner $signer,
        private CanonicalUrlGeneratorInterface $canonicalUrls,
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

        // Shown to: a customer group value. Date: a UTC date and time, or null for now
        $shownTo = $body['shownTo'] ?? null;
        if (!is_string($shownTo) || !CustomerGroupValue::isValid($shownTo)) {
            return BuilderRequest::badRequest(sprintf('The body needs shownTo, a customer group code, "%s" or "%s", got %s', CustomerGroupValue::GUEST, CustomerGroupValue::NO_GROUP, json_encode($shownTo)));
        }

        $date = $body['date'] ?? null;
        $at = null === $date ? null : self::dateTime($date);
        if (null !== $date && null === $at) {
            return BuilderRequest::badRequest(sprintf('The date must be null or a UTC date and time such as 2026-12-01T00:00:00Z, got %s', json_encode($date)));
        }

        // Where the working copy's page lives in the channel and locale, such as a homepage's channel's root. A snippet has no
        // page of its own, so it shows on a plain shop page, until #260 shows it on the page of one of its placements
        $workingCopy = $this->workingCopyProvider->getWorkingCopy($entry);
        $url = $workingCopy instanceof SnippetInterface
            ? $this->urlGenerator->generate(SnippetPreviewAction::ROUTE, ['_locale' => $localeCode], UrlGeneratorInterface::ABSOLUTE_URL)
            : $this->canonicalUrls->generate($workingCopy, $channel, $localeCode);
        if (null === $url) {
            return BuilderRequest::badRequest(sprintf('The entry has no URL in %s', $localeCode));
        }

        return new JsonResponse(['url' => $url . '?' . http_build_query([
            PreviewContext::QUERY_PARAMETER => $this->signer->sign(new Preview($entry->getUuid(), $channelCode, $localeCode, null, $shownTo, $at)),
        ])]);
    }

    /**
     * A time that exists, written in UTC as the builder writes it, or null
     */
    private static function dateTime(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value)) {
            return null;
        }

        $at = \DateTimeImmutable::createFromFormat(self::DATE_FORMAT, $value, new \DateTimeZone('UTC'));

        return false !== $at && $at->format(self::DATE_FORMAT) === $value ? $at : null;
    }
}
