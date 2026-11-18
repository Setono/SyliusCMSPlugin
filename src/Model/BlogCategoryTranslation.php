<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Setono\SyliusCMSPlugin\Field\Textarea;

/**
 * A blog category's translation, with its description in the column blog_category_description
 */
class BlogCategoryTranslation extends EntryTranslation implements BlogCategoryTranslationInterface
{
    #[Textarea(label: 'setono_sylius_cms.field.blog_category.description')]
    protected ?string $description = null;

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }
}
