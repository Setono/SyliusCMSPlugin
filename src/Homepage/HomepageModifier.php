<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Homepage;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Setono\SyliusCMSPlugin\Factory\HomepageFactoryInterface;
use Setono\SyliusCMSPlugin\Model\CodedRows;
use Setono\SyliusCMSPlugin\Model\HomepageInterface;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Publishing\InvalidEntrySettingsException;
use Setono\SyliusCMSPlugin\Repository\HomepageRepositoryInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Works out which rows to add and which to remove, and keeps the rows of the channels that stay: Doctrine removes rows
 * after it inserts them, so a new row for a channel that stays would clash with the old one's unique channel code. It
 * recognises two saves' clash by the homepage rows' unique key's name in the database's error message, which MySQL
 * and PostgreSQL both give
 */
final readonly class HomepageModifier implements HomepageModifierInterface
{
    /**
     * The homepage rows' unique key on the channel code, which the database names when a write breaks it. See
     * Homepage.orm.xml
     */
    private const CHANNEL_KEY = 'setono_sylius_cms__homepage_channel_uniq';

    public function __construct(
        private HomepageFactoryInterface $factory,
        private HomepageRepositoryInterface $repository,
        private TranslatorInterface $translator,
    ) {
    }

    public function checkHomepageOf(PageInterface $page, iterable $channelCodes, iterable $pageChannelCodes): void
    {
        $outside = array_values(array_unique(array_diff([...$channelCodes], $page->getHomepageChannelCodes(), [...$pageChannelCodes])));
        if ([] !== $outside) {
            sort($outside);

            throw new InvalidEntrySettingsException($this->translator->trans('setono_sylius_cms.homepage.not_in_channels', ['%channels%' => implode(', ', $outside)], 'validators'));
        }
    }

    public function inTransaction(EntityManagerInterface $manager, \Closure $writes): void
    {
        try {
            $manager->wrapInTransaction($writes);
        } catch (UniqueConstraintViolationException $e) {
            if (!str_contains($e->getMessage(), self::CHANNEL_KEY)) {
                throw $e;
            }

            throw new InvalidEntrySettingsException($this->translator->trans('setono_sylius_cms.homepage.taken', [], 'validators'), previous: $e);
        }
    }

    public function setHomepageOf(PageInterface $page, iterable $channelCodes): void
    {
        CodedRows::sync(
            $page->getHomepages()->toArray(),
            [...$channelCodes],
            static fn (HomepageInterface $homepage): string => $homepage->getChannelCode(),
            $page->removeHomepage(...),
            fn (string $code) => $page->addHomepage($this->factory->createForPage($page, $code)),
        );
    }

    public function takeOffOtherPages(PageInterface $page, iterable $channelCodes): array
    {
        $codes = array_values(array_unique([...$channelCodes]));
        if ([] === $codes) {
            return [];
        }

        $takenFrom = [];
        foreach ($this->repository->findByChannelCodes($codes) as $homepage) {
            $other = $homepage->getPage();
            if ($other === $page) {
                continue;
            }

            $other->removeHomepage($homepage);
            $takenFrom[$other->getUuid()] = $other;
        }

        return array_values($takenFrom);
    }
}
