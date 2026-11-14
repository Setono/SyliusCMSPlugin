<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Event;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Event\BeforeEntryPublish;
use Setono\SyliusCMSPlugin\Event\EntryCreated;
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
    public function a_publish_goes_ahead_unless_a_listener_vetoes_it(): void
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
    public function a_veto_stops_the_publish_and_the_listeners_after_it(): void
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
    public function a_publish_changes_shopper_output_for_the_published_entry(): void
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
    public function creating_an_entry_changes_no_shopper_output(): void
    {
        $event = new EntryCreated(self::UUID, 'page');

        self::assertNull(self::storefrontChange($event));
        self::assertSame(self::UUID, $event->uuid);
        self::assertSame('page', $event->contentType);
    }

    /**
     * @test
     */
    public function saving_on_save_settings_changes_shopper_output_for_the_entry_and_says_what_changed(): void
    {
        $event = new EntrySettingsChanged(self::UUID, [EntrySettingsChanged::ENABLED, EntrySettingsChanged::AUDIENCE]);

        self::assertSame([self::UUID], self::storefrontChange($event)?->getAffectedUuids());
        self::assertSame(self::UUID, $event->uuid);
        self::assertSame(['enabled', 'audience'], $event->changed);
    }

    /**
     * How cache invalidation would listen: once, for every event that changes shopper output
     */
    private static function storefrontChange(object $event): ?StorefrontChangedInterface
    {
        return $event instanceof StorefrontChangedInterface ? $event : null;
    }
}
