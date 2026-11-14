<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Model\Entry;
use Setono\SyliusCMSPlugin\Model\EntryTranslation;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Recipe;
use Sylius\Resource\Model\AbstractTranslation;
use Sylius\Resource\Model\TranslationInterface;

final class EntryTest extends TestCase
{
    /**
     * @test
     */
    public function an_entry_without_its_own_url_starts_with_an_empty_tree_without_a_layout(): void
    {
        $recipe = new Recipe();

        self::assertNull($recipe->getBlockTree()->layout);
        self::assertSame([], $recipe->getBlockTree()->blocks);
        self::assertSame('{"layout":null,"blocks":[]}', $recipe->getBlockTreeJson());
    }

    /**
     * @test
     */
    public function a_new_entry_and_translation_have_no_id(): void
    {
        self::assertNull((new Recipe())->getId());
        self::assertNull((new EntryTranslation())->getId());
    }

    /**
     * @test
     */
    public function its_translations_must_be_entry_translations(): void
    {
        $entry = new class() extends Entry {
            protected function createTranslation(): TranslationInterface
            {
                return new class() extends AbstractTranslation {
                };
            }
        };
        $entry->setCurrentLocale('en_US');

        $this->expectException(\LogicException::class);

        $entry->getTranslation();
    }
}
