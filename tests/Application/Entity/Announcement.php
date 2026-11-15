<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Application\Entity;

use Doctrine\ORM\Mapping as ORM;
use Setono\SyliusCMSPlugin\Attribute\Allow;
use Setono\SyliusCMSPlugin\Attribute\ContentType;
use Setono\SyliusCMSPlugin\Field\Text;
use Setono\SyliusCMSPlugin\Model\Entry;
use Setono\SyliusCMSPlugin\Model\EntryTranslation;
use Setono\SyliusCMSPlugin\Model\EntryTranslationInterface;

/**
 * The test application's own content type, as a project adds one: no URL of its own, a block tree, and a required
 * shared field. It has no translatable fields, so its translations are EntryTranslation itself
 */
#[ORM\Entity]
#[ContentType(code: 'announcement', label: 'app.cms.content_type.announcement', blocks: [new Allow('*')])]
class Announcement extends Entry
{
    #[ORM\Column(name: 'announcement_subtitle', type: 'string', nullable: true)]
    #[Text(label: 'app.cms.field.subtitle', required: true)]
    private ?string $subtitle = null;

    public function getSubtitle(): ?string
    {
        return $this->subtitle;
    }

    public function setSubtitle(?string $subtitle): void
    {
        $this->subtitle = $subtitle;
    }

    protected function createTranslation(): EntryTranslationInterface
    {
        return new EntryTranslation();
    }
}
