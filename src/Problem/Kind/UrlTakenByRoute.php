<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Problem\Kind;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Problem\Fix;
use Setono\SyliusCMSPlugin\Problem\Problem;
use Setono\SyliusCMSPlugin\Problem\ProblemKindInterface;
use Setono\SyliusCMSPlugin\Problem\Severity;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Routing\TakenUrlFinderInterface;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * A published entry's URL that a Sylius or app route takes, a warning: the route was added after the entry was
 * published, and wins over it, so shoppers get the route's page there. It's about the URLs shoppers have, so unlike
 * other kinds it reads the entry's rows, which hold its published content, whatever the working copy it's given has
 * drafted: the URLs setono:cms:check-routes reports at deploy, in the channels and locales the entry shows in. Each is
 * named once per locale, and fixed in the builder, by changing the slug and publishing. See "Routes added later" in
 * docs/spec/urls-and-seo.md and "Problems" in docs/spec/admin.md
 */
final readonly class UrlTakenByRoute implements ProblemKindInterface
{
    public const CODE = 'url_taken_by_route';

    public function __construct(
        private ContentTypeRegistryInterface $contentTypes,
        private TakenUrlFinderInterface $takenUrls,
        private EntryRepositoryInterface $entries,
    ) {
    }

    public function findIn(EntryInterface $entry): iterable
    {
        $rows = $this->rowsOf($entry);
        if (null === $rows || !$this->contentTypes->getByEntry($rows)->hasOwnUrl()) {
            return;
        }

        $found = [];
        foreach ($this->takenUrls->findLive($rows) as $taken) {
            if (in_array($taken->localeCode, $found, true)) {
                continue;
            }

            $found[] = $taken->localeCode;

            yield new Problem(
                self::CODE,
                Severity::Warning,
                $entry,
                new TranslatableMessage('setono_sylius_cms.problem.url_taken_by_route', [
                    '%url%' => sprintf('/%s/%s', $taken->localeCode, $taken->path),
                    '%route%' => $taken->route,
                ]),
                Fix::inBuilder($entry, null, $taken->localeCode),
                null,
                'slug',
                $taken->localeCode,
            );
        }
    }

    /**
     * The rows of the entry whose working copy is checked. A URL is only an entry's, for a route to take, once the
     * entry is published, and only a saved entry is
     */
    private function rowsOf(EntryInterface $entry): ?EntryInterface
    {
        $id = $entry->getId();

        return null === $id || !$entry->isPublished() ? null : $this->entries->findOneById($id);
    }
}
