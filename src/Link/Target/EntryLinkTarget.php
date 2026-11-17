<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Link\Target;

use Setono\SyliusCMSPlugin\Attribute\AsLinkTarget;
use Setono\SyliusCMSPlugin\Builder\Search\SearchInterface;
use Setono\SyliusCMSPlugin\Field\Reference;
use Setono\SyliusCMSPlugin\Field\ReferenceType;
use Setono\SyliusCMSPlugin\Link\ReferencingLinkTargetInterface;
use Setono\SyliusCMSPlugin\Link\ResolvedLink;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\EntryTranslationInterface;
use Setono\SyliusCMSPlugin\Rendering\ReferenceTypeLoaderInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Seo\CanonicalUrlGeneratorInterface;
use Symfony\Component\Uid\Uuid;

/**
 * An entry with a URL of its own, by its UUID. It leads to the entry's URL in the channel and locale, the channel's
 * root for a channel's homepage, named by its title, while the shopper may see the entry there ("References to
 * unavailable entries" in docs/spec/localization.md). See "Link targets" in docs/spec/extending.md
 */
#[AsLinkTarget(code: 'entry', label: 'setono_sylius_cms.link_target.entry')]
final readonly class EntryLinkTarget implements ReferencingLinkTargetInterface
{
    /**
     * @param ReferenceTypeLoaderInterface $entries the entries the shopper may see
     */
    public function __construct(
        private ReferenceTypeLoaderInterface $entries,
        private CanonicalUrlGeneratorInterface $urlGenerator,
        private SearchInterface $search,
    ) {
    }

    public function resolve(string $ref, RenderContext $context): ?ResolvedLink
    {
        $reference = $this->reference($ref);
        $entry = null === $reference ? null : $this->entries->load([$reference->id], $context)[$reference->id] ?? null;

        return null === $entry ? null : $this->link($entry, $ref, $context);
    }

    public function search(string $query, string $localeCode): iterable
    {
        foreach ($this->search->search($query, $localeCode) as $result) {
            yield $result->jsonSerialize();
        }
    }

    public function reference(string $ref): ?Reference
    {
        return Uuid::isValid($ref) ? new Reference(ReferenceType::ENTRY, strtolower($ref), false) : null;
    }

    public function link(object $record, string $ref, RenderContext $context): ?ResolvedLink
    {
        if (!$record instanceof EntryInterface) {
            return null;
        }

        $url = $this->urlGenerator->generate($record, $context->channel, $context->localeCode);
        if (null === $url) {
            return null;
        }

        // An entry the shopper may see has a title in the locale
        $translation = $record->getTranslations()->get($context->localeCode);

        return new ResolvedLink('entry', $ref, $url, $translation instanceof EntryTranslationInterface ? (string) $translation->getTitle() : '');
    }
}
