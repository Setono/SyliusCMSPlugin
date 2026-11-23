<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Publishing;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Clock\ClockInterface;
use Setono\SyliusCMSPlugin\Doctrine\EntityManagers;
use Setono\SyliusCMSPlugin\Event\EntrySettingsChanged;
use Setono\SyliusCMSPlugin\Homepage\HomepageModifierInterface;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Targeting\ChannelsAndAudienceModifierInterface;
use Setono\SyliusCMSPlugin\Validation\EntryUrlValidatorInterface;
use Setono\SyliusCMSPlugin\Validation\ValidationError;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Saves the on-save settings onto the entry's rows. It never reads or writes the draft, so changing a setting never
 * publishes someone's unfinished changes. See "What takes effect on save" in docs/spec/publishing.md. A change of the
 * channels that would give a published URL of the entry to two entries in a channel is refused, since it would take
 * effect at once (docs/spec/urls-and-seo.md#unique-per-channel-and-locale). Choosing a page as a channel's homepage takes
 * the channel off the page that was its homepage, whose "Homepage of" changes too, and is refused for a channel the page
 * isn't in, or when another save has just made another page that channel's homepage (docs/spec/urls-and-seo.md#homepage).
 * Only an entry of a content type ordered by position has a position
 */
final readonly class EntrySettingsSaver implements EntrySettingsSaverInterface
{
    /**
     * The homepage rows' unique key on the channel code, which the database names when a write breaks it. See
     * Homepage.orm.xml
     */
    private const HOMEPAGE_CHANNEL_KEY = 'setono_sylius_cms__homepage_channel_uniq';

    public function __construct(
        private ManagerRegistry $managerRegistry,
        private ClockInterface $clock,
        private EventDispatcherInterface $eventDispatcher,
        private ChannelsAndAudienceModifierInterface $channelsAndAudienceModifier,
        private EditRecorder $editRecorder,
        private EntryUrlValidatorInterface $urlValidator,
        private HomepageModifierInterface $homepageModifier,
        private ContentTypeRegistryInterface $contentTypes,
        private TranslatorInterface $translator,
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

        if (in_array(EntrySettingsChanged::POSITION, $changed, true) && !$this->contentTypes->getByEntry($entry)->order->byPosition) {
            throw new InvalidEntrySettingsException('Only an entry of a content type ordered by position has a position');
        }

        // The page whose homepages change, if they do
        $homepage = null;
        if (in_array(EntrySettingsChanged::HOMEPAGE, $changed, true)) {
            $homepage = $entry instanceof PageInterface ? $entry : throw new InvalidEntrySettingsException('Only a page can be a channel\'s homepage');

            // A page only becomes the homepage of a channel it's in. One it has left stays its homepage, which the
            // admin flags until the page is back in the channel or no longer its homepage
            $outside = array_values(array_diff($settings->homepageOf, $homepage->getHomepageChannelCodes(), $settings->channels));
            if ([] !== $outside) {
                throw new InvalidEntrySettingsException($this->translator->trans('setono_sylius_cms.homepage.not_in_channels', ['%channels%' => implode(', ', $outside)], 'validators'));
            }
        }

        $now = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));

        /** @var list<PageInterface> $takenFrom */
        $takenFrom = [];
        $this->inTransaction($manager, function () use ($manager, $entry, $settings, $homepage, $now, &$takenFrom): void {
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
     * Two saves that make different pages one channel's homepage at the same moment both find no other page to take it
     * off, so the second one's row breaks the homepage rows' unique channel code, which is refused with a message
     */
    private function inTransaction(EntityManagerInterface $manager, \Closure $writes): void
    {
        try {
            $manager->wrapInTransaction($writes);
        } catch (UniqueConstraintViolationException $e) {
            if (!str_contains($e->getMessage(), self::HOMEPAGE_CHANNEL_KEY)) {
                throw $e;
            }

            throw new InvalidEntrySettingsException($this->translator->trans('setono_sylius_cms.homepage.taken', [], 'validators'), previous: $e);
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
        $entry->setPosition($settings->position);
        $this->channelsAndAudienceModifier->setChannels($entry, $settings->channels);
        $this->channelsAndAudienceModifier->setAudience($entry, $settings->audience);

        if ($entry instanceof PageInterface) {
            $this->homepageModifier->setHomepageOf($entry, $settings->homepageOf);
        }
    }
}
