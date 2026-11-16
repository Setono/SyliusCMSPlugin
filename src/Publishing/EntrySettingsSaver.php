<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Publishing;

use Doctrine\Persistence\ManagerRegistry;
use Psr\Clock\ClockInterface;
use Setono\SyliusCMSPlugin\Doctrine\EntityManagers;
use Setono\SyliusCMSPlugin\Event\EntrySettingsChanged;
use Setono\SyliusCMSPlugin\Homepage\HomepageModifierInterface;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Targeting\ChannelsAndAudienceModifierInterface;
use Setono\SyliusCMSPlugin\Validation\EntryUrlValidatorInterface;
use Setono\SyliusCMSPlugin\Validation\ValidationError;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Saves the on-save settings onto the entry's rows. It never reads or writes the draft, so changing a setting never
 * publishes someone's unfinished changes. See "What takes effect on save" in docs/spec/publishing.md. A change of the
 * channels that would give a published URL of the entry to two entries in a channel is refused, since it would take
 * effect at once (docs/spec/urls-and-seo.md#unique-per-channel-and-locale). Choosing a page as a channel's homepage takes
 * the channel off the page that was its homepage, whose "Homepage of" changes too (docs/spec/urls-and-seo.md#homepage)
 */
final readonly class EntrySettingsSaver implements EntrySettingsSaverInterface
{
    public function __construct(
        private ManagerRegistry $managerRegistry,
        private ClockInterface $clock,
        private EventDispatcherInterface $eventDispatcher,
        private ChannelsAndAudienceModifierInterface $channelsAndAudienceModifier,
        private EditRecorder $editRecorder,
        private EntryUrlValidatorInterface $urlValidator,
        private HomepageModifierInterface $homepageModifier,
    ) {
    }

    public function save(EntryInterface $entry, EntrySettings $settings): void
    {
        $manager = EntityManagers::of($this->managerRegistry, $entry::class);

        $changed = $settings->changesFrom(EntrySettings::of($entry));
        if ([] === $changed) {
            return;
        }

        if (in_array(EntrySettingsChanged::CHANNELS, $changed, true)) {
            $clashes = $this->urlValidator->validateChannels($entry, $settings->channels);
            if ([] !== $clashes) {
                throw new InvalidEntrySettingsException(implode(' ', array_map(static fn (ValidationError $clash): string => $clash->message, $clashes)));
            }
        }

        // The page whose homepages change, if they do
        $homepage = null;
        if (in_array(EntrySettingsChanged::HOMEPAGE, $changed, true)) {
            $homepage = $entry instanceof PageInterface ? $entry : throw new InvalidEntrySettingsException('Only a page can be a channel\'s homepage');
        }

        $now = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));

        /** @var list<PageInterface> $takenFrom */
        $takenFrom = [];
        $manager->wrapInTransaction(function () use ($manager, $entry, $settings, $homepage, $now, &$takenFrom): void {
            // A channel has one homepage, which the rows' unique channel code holds to, and Doctrine inserts rows before
            // it deletes others: the pages that were the chosen channels' homepages lose them in a flush of their own
            if (null !== $homepage) {
                $takenFrom = $this->homepageModifier->takeOffOtherPages($homepage, $settings->homepageOf);
                foreach ($takenFrom as $page) {
                    $page->setChangedAt($now);
                }

                if ([] !== $takenFrom) {
                    $manager->flush();
                }
            }

            $this->apply($settings, $entry);
            $entry->setChangedAt($now);
            $this->editRecorder->record($entry);
        });

        // After the transaction commits
        $this->eventDispatcher->dispatch(new EntrySettingsChanged($entry->getUuid(), $changed));
        foreach ($takenFrom as $page) {
            $this->eventDispatcher->dispatch(new EntrySettingsChanged($page->getUuid(), [EntrySettingsChanged::HOMEPAGE]));
        }
    }

    /**
     * The channels, the audience and the homepages are rows beside the entry, so they change through the modifiers, which
     * create the rows. Only a page has homepages
     */
    private function apply(EntrySettings $settings, EntryInterface $entry): void
    {
        $entry->setEnabled($settings->enabled);
        $entry->setDateWindow($settings->dateWindow);
        $this->channelsAndAudienceModifier->setChannels($entry, $settings->channels);
        $this->channelsAndAudienceModifier->setAudience($entry, $settings->audience);

        if ($entry instanceof PageInterface) {
            $this->homepageModifier->setHomepageOf($entry, $settings->homepageOf);
        }
    }
}
