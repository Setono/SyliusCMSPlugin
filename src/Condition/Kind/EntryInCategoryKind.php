<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Condition\Kind;

use Setono\SyliusCMSPlugin\Attribute\AsConditionKind;
use Setono\SyliusCMSPlugin\Collection\IndexedFields;
use Setono\SyliusCMSPlugin\Condition\ConditionKindInterface;
use Setono\SyliusCMSPlugin\Condition\Reads;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;

/**
 * Matches when the entry being shown, such as a blog post whose layout renders the block, is of a collection with
 * categories and is in any of the categories. Its categories are read from the entry as it's shown, its published
 * content or, in a preview, its working copy. A deleted category, or one the shopper may not see, matches nothing.
 * See "Condition kinds" in docs/spec/targeting.md
 */
#[AsConditionKind(code: self::CODE, label: 'setono_sylius_cms.condition_kind.entry_in_category', needs: ['entry'], reads: [Reads::HostPage], params: EntryInCategoryParams::class)]
final readonly class EntryInCategoryKind implements ConditionKindInterface
{
    public const CODE = 'entry_in_category';

    public function __construct(private IndexedFields $indexedFields)
    {
    }

    public function matches(object $params, RenderContext $context): bool
    {
        if (!$params instanceof EntryInCategoryParams) {
            throw new \InvalidArgumentException(sprintf('The entry in category condition takes %s, got %s', EntryInCategoryParams::class, $params::class));
        }

        $entry = $context->get('entry');
        if (!$entry instanceof EntryInterface || null === $params->categories) {
            return false;
        }

        $categories = $this->indexedFields->categoriesOf($entry);
        foreach ($params->categories as $category) {
            if ($category instanceof EntryInterface && in_array($category->getUuid(), $categories, true)) {
                return true;
            }
        }

        return false;
    }
}
