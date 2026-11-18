<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PrePersistEventArgs;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Doctrine\EntryPositionListener;
use Setono\SyliusCMSPlugin\Model\EntryTranslation;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Tests\Application\Entity\Announcement;

/**
 * Where a new entry of a content type ordered by position goes, last, is tested against a database in
 * tests/Functional/Admin/EntryOrderTest.php
 */
final class EntryPositionListenerTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_gives_no_position_to_an_entry_of_a_content_type_ordered_otherwise(): void
    {
        $page = new Page();

        $this->persist($page);

        self::assertNull($page->getPosition());
    }

    /**
     * An entry that comes with a position, such as an imported one, keeps it
     *
     * @test
     */
    public function it_keeps_the_position_an_entry_comes_with(): void
    {
        $announcement = new Announcement();
        $announcement->setPosition(7);

        $this->persist($announcement);

        self::assertSame(7, $announcement->getPosition());
    }

    /**
     * @test
     */
    public function it_leaves_what_is_not_an_entry_alone(): void
    {
        // It queries nothing
        $this->persist(new PageTranslation());
    }

    private function persist(object $object): void
    {
        $manager = $this->prophesize(EntityManagerInterface::class);
        $manager->createQueryBuilder()->shouldNotBeCalled();

        (new EntryPositionListener(new ContentTypeRegistry([
            'page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page'],
            'announcement' => ['model' => Announcement::class, 'translation' => EntryTranslation::class, 'resource' => 'app.announcement'],
        ])))->prePersist(new PrePersistEventArgs($object, $manager->reveal()));
    }
}
