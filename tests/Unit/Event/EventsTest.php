<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Event;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Event\BeforeEntryDelete;
use Setono\SyliusCMSPlugin\Event\BeforeEntryPublish;
use Setono\SyliusCMSPlugin\Event\EntryCreated;
use Setono\SyliusCMSPlugin\Event\EntryDeleted;
use Setono\SyliusCMSPlugin\Event\EntryPublished;
use Setono\SyliusCMSPlugin\Event\EntrySettingsChanged;
use Setono\SyliusCMSPlugin\Event\StorefrontChangedInterface;
use Setono\SyliusCMSPlugin\Model\Page;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class EventsTest extends TestCase
{
    private const UUID = '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e';

    /**
     * @test
     */
    public function it_lets_a_publish_go_ahead_unless_a_listener_vetoes_it(): void
    {
        $page = new Page();
        $event = new BeforeEntryPublish($page, false);

        self::assertSame($page, $event->entry);
        self::assertFalse($event->scheduled);
        self::assertFalse($event->isVetoed());
        self::assertNull($event->getVetoMessage());
        self::assertFalse($event->isPropagationStopped());
    }

    /**
     * @test
     */
    public function it_stops_the_publish_and_the_listeners_after_a_veto(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(BeforeEntryPublish::class, static fn (BeforeEntryPublish $event) => $event->veto('Not on a Sunday'), 10);
        $dispatcher->addListener(BeforeEntryPublish::class, static fn (BeforeEntryPublish $event) => $event->veto('Never'));

        $event = $dispatcher->dispatch(new BeforeEntryPublish(new Page(), true));

        self::assertTrue($event->scheduled);
        self::assertTrue($event->isVetoed());
        self::assertSame('Not on a Sunday', $event->getVetoMessage());
        self::assertTrue($event->isPropagationStopped());
    }

    /**
     * @test
     */
    public function it_changes_shopper_output_for_the_published_entry_on_a_publish(): void
    {
        $event = new EntryPublished(self::UUID, 'page', ['en_US' => 'about'], ['en_US' => 'about-us'], false);

        self::assertSame([self::UUID], self::storefrontChange($event)?->getAffectedUuids());
        self::assertSame(self::UUID, $event->uuid);
        self::assertSame('page', $event->contentType);
        self::assertSame(['en_US' => 'about'], $event->oldSlugs);
        self::assertSame(['en_US' => 'about-us'], $event->newSlugs);
        self::assertFalse($event->scheduled);
    }

    /**
     * @test
     */
    public function it_changes_no_shopper_output_when_it_creates_an_entry(): void
    {
        $event = new EntryCreated(self::UUID, 'page');

        self::assertNull(self::storefrontChange($event));
        self::assertSame(self::UUID, $event->uuid);
        self::assertSame('page', $event->contentType);
    }

    /**
     * @test
     */
    public function it_changes_shopper_output_for_the_entry_on_saving_on_save_settings_and_says_what_changed(): void
    {
        $event = new EntrySettingsChanged(self::UUID, [EntrySettingsChanged::ENABLED, EntrySettingsChanged::AUDIENCE]);

        self::assertSame([self::UUID], self::storefrontChange($event)?->getAffectedUuids());
        self::assertSame(self::UUID, $event->uuid);
        self::assertSame(['enabled', 'audience'], $event->changed);
    }

    /**
     * @test
     */
    public function it_lets_a_delete_go_ahead_unless_a_listener_vetoes_it(): void
    {
        $page = new Page();
        $event = new BeforeEntryDelete($page);

        self::assertSame($page, $event->entry);
        self::assertFalse($event->isVetoed());
        self::assertNull($event->getVetoMessage());
        self::assertFalse($event->isPropagationStopped());
    }

    /**
     * @test
     */
    public function it_stops_the_delete_and_the_listeners_after_a_veto(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(BeforeEntryDelete::class, static fn (BeforeEntryDelete $event) => $event->veto('The homepage stays'), 10);
        $dispatcher->addListener(BeforeEntryDelete::class, static fn (BeforeEntryDelete $event) => $event->veto('Never'));

        $event = $dispatcher->dispatch(new BeforeEntryDelete(new Page()));

        self::assertTrue($event->isVetoed());
        self::assertSame('The homepage stays', $event->getVetoMessage());
        self::assertTrue($event->isPropagationStopped());
    }

    /**
     * @test
     */
    public function it_changes_shopper_output_for_the_deleted_entry_on_a_delete(): void
    {
        $event = new EntryDeleted(self::UUID, 'page');

        self::assertSame([self::UUID], self::storefrontChange($event)?->getAffectedUuids());
        self::assertSame(self::UUID, $event->uuid);
        self::assertSame('page', $event->contentType);
    }

    /**
     * How cache invalidation would listen: once, for every event that changes shopper output
     */
    private static function storefrontChange(object $event): ?StorefrontChangedInterface
    {
        return $event instanceof StorefrontChangedInterface ? $event : null;
    }
}
