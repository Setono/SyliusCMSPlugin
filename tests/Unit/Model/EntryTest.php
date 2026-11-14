<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Model\Entry;
use Setono\SyliusCMSPlugin\Model\EntryTranslation;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Recipe;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
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
    public function a_clone_is_a_copy_whose_translations_and_tree_change_without_the_original(): void
    {
        $page = new Page();
        $page->setCurrentLocale('en_US');
        $page->setTitle('About us');
        $page->getTranslation('da_DK')->setTitle('Om os');

        $copy = clone $page;
        $copy->setTitle('About');
        $copy->getTranslation('da_DK')->setTitle('Om');
        $copy->setBlockTree(new StoredTree(null, [new StoredBlock('0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f', 'heading', 1)]));

        self::assertSame($page->getUuid(), $copy->getUuid());
        self::assertSame(['en_US', 'da_DK'], $copy->getTranslations()->getKeys());
        self::assertSame($copy, $copy->getTranslation('da_DK')->getTranslatable());
        self::assertSame('About', $copy->getTitle());
        self::assertSame('Om', $copy->getTranslation('da_DK')->getTitle());

        self::assertSame('About us', $page->getTitle());
        self::assertSame('Om os', $page->getTranslation('da_DK')->getTitle());
        self::assertSame($page, $page->getTranslation('da_DK')->getTranslatable());
        self::assertCount(2, $page->getTranslations());
        self::assertSame([], $page->getBlockTree()->blocks);
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
