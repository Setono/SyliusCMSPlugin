<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\EntryFormat;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\EntryFormat\EntryNormalizer;
use Setono\SyliusCMSPlugin\Json\CanonicalJson;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;

final class EntryNormalizerTest extends TestCase
{
    /**
     * @test
     */
    public function it_writes_an_entry_in_the_entry_format(): void
    {
        $page = new Page();
        foreach (['en_US' => ['About us', 'about-us'], 'da_DK' => ['Om os', 'om-os']] as $locale => [$title, $slug]) {
            $page->setCurrentLocale($locale);
            $page->setTitle($title);
            $page->setSlug($slug);
        }

        $json = CanonicalJson::encode($this->normalizer()->normalize($page));

        self::assertSame(
            sprintf(
                '{"uuid":"%s","contentType":"page","version":1,"createdAt":"%s","fields":{},"translations":{"da_DK":{"shown":true,"fields":{"slug":"om-os","title":"Om os"},"translated":{}},"en_US":{"shown":true,"fields":{"slug":"about-us","title":"About us"},"translated":{}}},"tree":{"layout":{"code":"standard","version":1,"fields":{},"translated":{}},"blocks":[]}}',
                $page->getUuid(),
                $page->getCreatedAt()->format('Y-m-d\TH:i:s\Z'),
            ),
            $json,
        );
    }

    /**
     * @test
     */
    public function it_writes_created_at_in_utc(): void
    {
        $page = new Page();
        $createdAt = new \ReflectionProperty($page, 'createdAt');
        $createdAt->setValue($page, new \DateTimeImmutable('2026-11-14 12:30:00', new \DateTimeZone('Europe/Copenhagen')));

        $json = CanonicalJson::encode($this->normalizer()->normalize($page));

        self::assertStringContainsString('"createdAt":"2026-11-14T11:30:00Z"', $json);
    }

    /**
     * @test
     */
    public function it_writes_an_entry_without_translations_with_an_empty_map(): void
    {
        self::assertStringContainsString('"translations":{}', CanonicalJson::encode($this->normalizer()->normalize(new Page())));
    }

    private function normalizer(): EntryNormalizer
    {
        return new EntryNormalizer(new ContentTypeRegistry([
            'page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page'],
        ]));
    }
}
