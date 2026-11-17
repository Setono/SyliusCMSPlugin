<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Setono\SyliusCMSPlugin\Event\MediaItemReplaced;
use Setono\SyliusCMSPlugin\Media\DeleteReplacedFile;
use Setono\SyliusCMSPlugin\Media\MediaFileReplacerInterface;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;
use Setono\SyliusCMSPlugin\Tests\Support\MediaItems;
use Setono\SyliusCMSPlugin\Tests\Support\SentMessages;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Event\SendMessageToTransportsEvent;
use Symfony\Component\Messenger\Stamp\DelayStamp;

/**
 * A replaced file is deleted with its variants by a delayed message once media.grace_period is over, and the message
 * is sent only after the replace's transaction commits, so a replace that rolls back deletes nothing. See "Replacing
 * a file" in docs/spec/media.md
 */
final class ReplacedFileDeleteTest extends KernelTestCase
{
    use ClockSensitiveTrait;
    use MediaItems;
    use SentMessages;

    protected function setUp(): void
    {
        self::mockTime('2026-11-20 10:00:00');
        self::forgetSentMessages();
    }

    /**
     * @test
     */
    public function it_deletes_the_old_file_once_the_grace_period_is_over_with_a_message_sent_after_the_transaction_commits(): void
    {
        $mediaItem = $this->uploadMediaItem('summer.png', self::png());
        $oldPath = $mediaItem->getPath();

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $outside = $connection->getTransactionNestingLevel();

        /** @var list<array{object, int, mixed}> $sent each message, the transaction nesting level, and the media item's path in the database */
        $sent = [];
        /** @var EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = self::getContainer()->get('event_dispatcher');
        $eventDispatcher->addListener(SendMessageToTransportsEvent::class, static function (SendMessageToTransportsEvent $event) use (&$sent, $connection, $mediaItem): void {
            $sent[] = [$event->getEnvelope()->getMessage(), $connection->getTransactionNestingLevel(), $connection->fetchOne('SELECT path FROM setono_sylius_cms__media_item WHERE uuid = ?', [$mediaItem->getUuid()])];
        });

        $this->replace($mediaItem, 'summer-2027.png', self::png(rgb: [0, 120, 0]));
        $newPath = $mediaItem->getPath();

        self::assertCount(1, $sent);
        [[$message, $level, $storedPath]] = $sent;
        self::assertEquals(new DeleteReplacedFile($mediaItem->getUuid(), $oldPath), $message);
        self::assertSame($outside, $level);
        self::assertSame($newPath, $storedPath);

        $messages = self::sentMessages();
        self::assertCount(1, $messages);
        self::assertSame(86_400_000, $messages[0]->last(DelayStamp::class)?->getDelay());

        // Kept for the grace period
        self::mockTime('2026-11-21 09:59:00');
        self::assertSame(0, self::consumeMessages());
        self::assertFileExists(self::mediaDirectory() . '/' . $oldPath);

        // Then deleted, and the new file stays
        self::mockTime('2026-11-21 10:00:01');
        self::assertSame(1, self::consumeMessages());
        self::assertCount(1, self::acknowledgedMessages());
        self::assertFileDoesNotExist(self::mediaDirectory() . '/' . $oldPath);
        self::assertFileExists(self::mediaDirectory() . '/' . $newPath);
    }

    /**
     * @test
     */
    public function it_sends_nothing_dispatches_nothing_and_keeps_the_old_file_when_the_replace_rolls_back(): void
    {
        $mediaItem = $this->uploadMediaItem('summer.png', self::png());
        $uuid = $mediaItem->getUuid();
        $oldPath = $mediaItem->getPath();

        $replaced = [];
        /** @var EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = self::getContainer()->get('event_dispatcher');
        $eventDispatcher->addListener(MediaItemReplaced::class, static function (MediaItemReplaced $event) use (&$replaced): void {
            $replaced[] = $event;
        });

        // The flush that would point the media item at the new file fails
        /** @var EntityManagerInterface $manager */
        $manager = self::getContainer()->get('setono_sylius_cms.manager.media_item');
        $manager->getEventManager()->addEventListener([Events::onFlush], new class() {
            public function onFlush(OnFlushEventArgs $args): void
            {
                foreach ($args->getObjectManager()->getUnitOfWork()->getScheduledEntityUpdates() as $entity) {
                    if ($entity instanceof MediaItemInterface) {
                        throw new \RuntimeException('The database went away');
                    }
                }
            }
        });

        try {
            $this->replace($mediaItem, 'summer-2027.png', self::png(rgb: [0, 120, 0]));
            self::fail('The replace should have failed');
        } catch (\RuntimeException $e) {
            self::assertSame('The database went away', $e->getMessage());
        }

        self::assertSame([], self::sentMessages());
        self::assertSame([], $replaced);
        self::assertFileExists(self::mediaDirectory() . '/' . $oldPath);
        // The new file was deleted again, so the media item's directory holds only the old one
        self::assertSame([$oldPath], self::filesOf($uuid));

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        self::assertSame($oldPath, $connection->fetchOne('SELECT path FROM setono_sylius_cms__media_item WHERE uuid = ?', [$uuid]));
    }

    private function replace(MediaItemInterface $mediaItem, string $filename, string $content): void
    {
        /** @var MediaFileReplacerInterface $replacer */
        $replacer = self::getContainer()->get(MediaFileReplacerInterface::class);
        $replacer->replace($mediaItem, $this->uploadedFile($filename, $content), $filename);
    }

    /**
     * The paths of the files under the media item's UUID in the storage
     *
     * @return list<string>
     */
    private static function filesOf(string $uuid): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::mediaDirectory() . '/' . $uuid, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            \assert($file instanceof \SplFileInfo);
            $files[] = substr($file->getPathname(), strlen(self::mediaDirectory()) + 1);
        }

        return $files;
    }
}
