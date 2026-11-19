<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Publishing;

use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Publishing\EditRecorder;
use Setono\SyliusCMSPlugin\Publishing\EntryReorderer;
use Setono\SyliusCMSPlugin\Publishing\InvalidEntrySettingsException;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

/**
 * Reordering itself is tested against a database in tests/Functional/Admin/EntryOrderTest.php, and the new positions in
 * EntryPositionsTest
 */
final class EntryReordererTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_refuses_to_order_the_entries_of_a_content_type_ordered_otherwise(): void
    {
        $this->expectException(InvalidEntrySettingsException::class);
        $this->expectExceptionMessage('The content type "page" is not ordered by position');

        $this->reorderer()->reorder('page', []);
    }

    /**
     * @test
     */
    public function it_refuses_to_order_the_entries_of_a_content_type_the_code_does_not_have(): void
    {
        $this->expectException(InvalidEntrySettingsException::class);
        $this->expectExceptionMessage('There is no content type with the code "faq_item"');

        $this->reorderer()->reorder('faq_item', []);
    }

    private function reorderer(): EntryReorderer
    {
        $managerRegistry = $this->prophesize(ManagerRegistry::class);
        $managerRegistry->getManagerForClass(Argument::any())->shouldNotBeCalled();
        $clock = new MockClock('2026-11-18T10:00:00Z');

        return new EntryReorderer(
            $managerRegistry->reveal(),
            $clock,
            new EventDispatcher(),
            new EditRecorder(new TokenStorage(), $clock),
            new ContentTypeRegistry(['page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page']]),
        );
    }
}
