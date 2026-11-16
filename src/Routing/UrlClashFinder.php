<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Routing;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Query\UrlClaimsQueryInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;

/**
 * Turns each of the entry's slugs into its URL, with its content type's prefix, and asks for the entries that have that
 * URL by the slug it holds for each content type. Those in a channel the entry is in clash with it
 */
final readonly class UrlClashFinder implements UrlClashFinderInterface
{
    public function __construct(
        private ContentTypeRegistryInterface $contentTypes,
        private UrlClaimsQueryInterface $claims,
    ) {
    }

    public function findPublished(EntryInterface $entry, array $slugs, array $channelCodes): array
    {
        return $this->find($entry, $slugs, $channelCodes, $this->claims->published(...));
    }

    public function findDrafted(EntryInterface $entry, array $slugs, array $channelCodes): array
    {
        return $this->find($entry, $slugs, $channelCodes, $this->claims->drafted(...));
    }

    /**
     * @param array<string, string> $slugs
     * @param list<string> $channelCodes
     * @param \Closure(string, array<string, string>, string): list<UrlClaim> $claims
     *
     * @return list<UrlClash>
     */
    private function find(EntryInterface $entry, array $slugs, array $channelCodes, \Closure $claims): array
    {
        // An entry in no channel has its URLs nowhere
        if ([] === $channelCodes) {
            return [];
        }

        $contentType = $this->contentTypes->getByEntry($entry);

        $clashes = [];
        foreach ($slugs as $localeCode => $slug) {
            $path = EntryPath::ofSlug($contentType, $slug);
            if (null === $path) {
                continue;
            }

            foreach ($claims($localeCode, EntryPath::slugs($this->contentTypes->all(), $path), $entry->getUuid()) as $claim) {
                $shared = array_values(array_intersect($channelCodes, $claim->channelCodes));
                if ([] !== $shared) {
                    $clashes[] = new UrlClash($localeCode, $path, $claim, $shared);
                }
            }
        }

        return $clashes;
    }
}
