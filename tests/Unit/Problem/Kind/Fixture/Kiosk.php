<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Problem\Kind\Fixture;

use Setono\SyliusCMSPlugin\Attribute\ContentType;
use Setono\SyliusCMSPlugin\Field\Products;
use Setono\SyliusCMSPlugin\Field\RichText;
use Setono\SyliusCMSPlugin\Field\Toggle;
use Setono\SyliusCMSPlugin\Model\Entry;
use Setono\SyliusCMSPlugin\Model\EntryTranslation;
use Setono\SyliusCMSPlugin\Model\EntryTranslationInterface;

/**
 * A project's content type whose own fields hold what the problem kinds find in blocks elsewhere: a toggle, a list a
 * source fills and rich text with links. Its properties take any value, as a column holds what was stored
 */
#[ContentType(code: 'kiosk', label: 'app.cms.content_type.kiosk', prefix: 'kiosks')]
class Kiosk extends Entry
{
    #[Toggle(label: 'app.cms.field.open')]
    public mixed $open = null;

    #[Products(label: 'app.cms.field.picks')]
    public mixed $picks = null;

    #[RichText(label: 'app.cms.field.intro')]
    public mixed $intro = null;

    protected function createTranslation(): EntryTranslationInterface
    {
        return new EntryTranslation();
    }
}
