<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Builder\Search;

use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Setono\SyliusCMSPlugin\Context\Needs;
use Setono\SyliusCMSPlugin\Doctrine\EntityManagers;
use Setono\SyliusCMSPlugin\Doctrine\SearchPattern;
use Setono\SyliusCMSPlugin\Localization\FallbackValues;
use Setono\SyliusCMSPlugin\Model\EntryTranslation;
use Setono\SyliusCMSPlugin\Model\EntryTranslationInterface;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Uuid;

/**
 * Searches the snippets a Snippet field can show, by their title in any locale, ignoring case, published or not, newest
 * first. The picker narrows the search with the request's query parameters: `provides`, the context keys the edited
 * entry provides, comma-separated, leaves out the snippets whose published content declares more, and `entry`, the
 * edited entry's UUID, leaves out the entry itself. Looking snippets up by UUID isn't narrowed, so a field names what it
 * holds. Each is named by its title in the admin user's locale, along its fallback chain. See "Snippet references" in
 * docs/spec/builder.md
 *
 * @internal
 */
final readonly class SnippetSearch implements SearchInterface
{
    /** The most results it answers */
    public const LIMIT = 20;

    /** The most snippets it reads before it narrows them by context */
    private const READ = 200;

    /**
     * @param class-string<SnippetInterface> $snippetClass
     */
    public function __construct(
        private ManagerRegistry $managerRegistry,
        private string $snippetClass,
        private FallbackValues $fallbackValues,
        private RequestStack $requestStack,
    ) {
    }

    public function search(string $query, string $localeCode): array
    {
        $snippets = $this->snippets()
            ->andWhere(sprintf(
                'EXISTS (SELECT matched.id FROM %1$s matched WHERE matched.translatable = snippet AND LOWER(matched.title) LIKE :query %2$s)',
                EntryTranslation::class,
                SearchPattern::ESCAPE,
            ))
            ->setParameter('query', SearchPattern::containing($query))
            ->setMaxResults(self::READ)
        ;

        $request = $this->requestStack->getCurrentRequest();
        $provides = $request?->query->get('provides');
        $entry = $request?->query->get('entry');

        /** @var list<SnippetInterface> $found */
        $found = $snippets->getQuery()->getResult();
        $fitting = array_filter($found, static fn (SnippetInterface $snippet): bool => $snippet->getUuid() !== $entry &&
            (!is_string($provides) || Needs::areMet($snippet->getContext(), self::keys($provides))));

        return $this->results(array_slice(array_values($fitting), 0, self::LIMIT), $localeCode);
    }

    public function find(array $refs, string $localeCode): array
    {
        $uuids = array_values(array_map(strtolower(...), array_filter($refs, Uuid::isValid(...))));
        if ([] === $uuids) {
            return [];
        }

        /** @var list<SnippetInterface> $found */
        $found = $this->snippets()->andWhere('snippet.uuid IN (:uuids)')->setParameter('uuids', $uuids)->getQuery()->getResult();

        return $this->results($found, $localeCode);
    }

    private function snippets(): QueryBuilder
    {
        return EntityManagers::of($this->managerRegistry, $this->snippetClass)->createQueryBuilder()
            ->select('snippet')
            ->from($this->snippetClass, 'snippet')
            ->addOrderBy('snippet.id', 'DESC')
        ;
    }

    /**
     * @return list<string>
     */
    private static function keys(string $provides): array
    {
        return array_values(array_filter(explode(',', $provides), static fn (string $key): bool => '' !== $key));
    }

    /**
     * @param list<SnippetInterface> $snippets
     *
     * @return list<SearchResult>
     */
    private function results(array $snippets, string $localeCode): array
    {
        return array_map(fn (SnippetInterface $snippet): SearchResult => new SearchResult($snippet->getUuid(), $this->title($snippet, $localeCode)), $snippets);
    }

    /**
     * The title in the locale along its fallback chain, or else in any locale the snippet has one in
     */
    private function title(SnippetInterface $snippet, string $localeCode): string
    {
        $title = $this->fallbackValues->entryValue($snippet, 'title', $localeCode);
        if (is_string($title)) {
            return $title;
        }

        foreach ($snippet->getTranslations() as $translation) {
            if ($translation instanceof EntryTranslationInterface && null !== $translation->getTitle() && '' !== $translation->getTitle()) {
                return $translation->getTitle();
            }
        }

        return $snippet->getUuid();
    }
}
