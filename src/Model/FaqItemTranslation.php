<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Setono\SyliusCMSPlugin\Field\RichText;

/**
 * A FAQ item's translation, whose title is the question, with the answer in the column faq_item_answer
 */
class FaqItemTranslation extends EntryTranslation implements FaqItemTranslationInterface
{
    /**
     * A ProseMirror document, as rich text is stored
     *
     * @var array<array-key, mixed>|null
     */
    #[RichText(label: 'setono_sylius_cms.field.faq_item.answer')]
    protected ?array $answer = null;

    public function getAnswer(): ?array
    {
        return $this->answer;
    }

    public function setAnswer(?array $answer): void
    {
        $this->answer = $answer;
    }
}
