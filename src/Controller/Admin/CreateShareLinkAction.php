<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\ShareLink\ShareLinkCreatorInterface;
use Setono\SyliusCMSPlugin\ShareLink\ShareLinkUnavailableException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * The overview page's Share draft, which makes a share link of the entry's draft for the channel and locale picked,
 * as the builder's share_link_create does, and comes back to the overview page, where the Share links section lists
 * it. The form posts the pick as `target`, "<channel code>|<locale code>", and for a snippet the UUID of the placement
 * whose host page shows it as `placement`, empty for a plain page, which shows it with the sample object a preview of
 * the placement starts with. See "Entry overview page" in docs/spec/admin.md
 */
final readonly class CreateShareLinkAction
{
    public const TARGET_SEPARATOR = '|';

    public function __construct(
        private EntryOverviewRequest $overviewRequest,
        private ShareLinkCreatorInterface $creator,
    ) {
    }

    public function __invoke(Request $request, string $uuid): RedirectResponse
    {
        return $this->overviewRequest->submit($request, $uuid, function (EntryInterface $entry) use ($request): array {
            $target = explode(self::TARGET_SEPARATOR, (string) $request->request->get('target'), 2);
            if (2 !== count($target)) {
                return ['error', 'setono_sylius_cms.share_link_target_missing'];
            }

            try {
                $placement = $request->request->get('placement');
                $this->creator->create($entry, $target[0], $target[1], is_string($placement) && '' !== $placement ? $placement : null);
            } catch (ShareLinkUnavailableException $e) {
                return ['error', $e->getMessage()];
            }

            return ['success', 'setono_sylius_cms.share_link_created'];
        });
    }
}
