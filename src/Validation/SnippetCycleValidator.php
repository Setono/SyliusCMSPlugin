<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Validation;

use Setono\SyliusCMSPlugin\Context\EntryTitle;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Tree\SnippetCycles;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The Snippet block's publish rule: a snippet whose Snippet blocks lead back to it, directly or through the published
 * trees of the snippets they show, is refused at each such block, naming the way by the snippets' titles, such as
 * "Footer → Promo → Footer". Only snippets show snippets, so only a snippet is checked. Imported or older data can
 * still hold such a block, which the snippet_inside_itself problem flags. See "Validation" in docs/spec/publishing.md
 * and "Snippet" in docs/spec/blocks.md
 *
 * @internal
 */
final readonly class SnippetCycleValidator
{
    public function __construct(
        private SnippetCycles $snippetCycles,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @param EntryInterface $workingCopy what would be published
     *
     * @return list<ValidationError>
     */
    public function validate(EntryInterface $workingCopy): array
    {
        if (!$workingCopy instanceof SnippetInterface) {
            return [];
        }

        $title = EntryTitle::of($workingCopy);

        $errors = [];
        foreach ($this->snippetCycles->in($workingCopy, $workingCopy->getBlockTree()) as $cycle) {
            $way = implode(' → ', [$title, ...array_map(EntryTitle::of(...), $cycle->through), $title]);

            $errors[] = new ValidationError(
                $cycle->reference->block,
                $cycle->reference->field,
                $cycle->reference->locale,
                $this->translator->trans('setono_sylius_cms.block.snippet_inside_itself', ['%path%' => $way], 'validators'),
            );
        }

        return $errors;
    }
}
