<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Problem\Kind;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Problem\Fix;
use Setono\SyliusCMSPlugin\Problem\Problem;
use Setono\SyliusCMSPlugin\Problem\ProblemKindInterface;
use Setono\SyliusCMSPlugin\Problem\Severity;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Routing\Slug;
use Setono\SyliusCMSPlugin\Routing\TakenUrlFinderInterface;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * A published entry's URL that a Sylius or app route takes, a warning: the route was added after the entry was
 * published, and wins over it, so shoppers get the route's page there. It's checked in the entry's channels and
 * locales, with the slugs of the entry as it's checked, and named once per locale. It's fixed in the builder, by
 * changing the slug. setono:cms:check-routes reports the same at deploy. See "Routes added later" in
 * docs/spec/urls-and-seo.md and "Problems" in docs/spec/admin.md
 */
final readonly class UrlTakenByRoute implements ProblemKindInterface
{
    public const CODE = 'url_taken_by_route';

    public function __construct(
        private ContentTypeRegistryInterface $contentTypes,
        private TakenUrlFinderInterface $takenUrls,
    ) {
    }

    public function findIn(EntryInterface $entry): iterable
    {
        // A URL is only an entry's, for a route to take, once the entry is published
        if (!$entry->isPublished() || !$this->contentTypes->getByEntry($entry)->hasOwnUrl()) {
            return;
        }

        $slugs = [];
        foreach ($entry->getTranslations() as $localeCode => $translation) {
            $slug = $translation->getSlug();
            if (Slug::isValid($slug)) {
                $slugs[(string) $localeCode] = (string) $slug;
            }
        }

        $found = [];
        foreach ($this->takenUrls->find($entry, $slugs, $entry->getChannelCodes()) as $taken) {
            if (isset($found[$taken->localeCode])) {
                continue;
            }

            $found[$taken->localeCode] = true;

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
}
