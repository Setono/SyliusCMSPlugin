<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin;

use Setono\SyliusCMSPlugin\Model\EntryAudienceGroupInterface;
use Setono\SyliusCMSPlugin\Model\EntryChannelInterface;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api\BuilderApiTestCase;
use Setono\SyliusCMSPlugin\Tests\Functional\Fixture\Entity\EntryAudienceGroup;
use Setono\SyliusCMSPlugin\Tests\Functional\Fixture\Entity\EntryChannel;
use Setono\SyliusCMSPlugin\Tests\Functional\Fixture\OverriddenRowModelsKernel;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * A project that overrides the entry_channel and entry_audience_group models gets rows of its own subclasses wherever
 * an entry's channels or audience change
 */
final class OverriddenRowModelsTest extends BuilderApiTestCase
{
    protected static function getKernelClass(): string
    {
        return OverriddenRowModelsKernel::class;
    }

    /**
     * @test
     */
    public function it_runs_with_the_channel_and_audience_group_models_overridden(): void
    {
        self::bootKernel();

        self::assertSame(EntryChannel::class, self::getContainer()->getParameter('setono_sylius_cms.model.entry_channel.class'));
        self::assertSame(EntryAudienceGroup::class, self::getContainer()->getParameter('setono_sylius_cms.model.entry_audience_group.class'));
    }

    /**
     * @test
     */
    public function it_stores_the_overridden_channel_rows_of_a_new_entry(): void
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');
        self::createChannel('B2B');

        $client->request('GET', '/admin/cms/entries/page/new');
        $client->submitForm('Create', [
            'setono_sylius_cms_create_entry[title]' => 'About us',
            'setono_sylius_cms_create_entry[slug]' => 'about-us',
        ]);
        self::assertResponseRedirects();

        /** @var RepositoryInterface<PageInterface> $repository */
        $repository = self::getContainer()->get('setono_sylius_cms.repository.page');
        $page = $repository->findOneBy([]);
        self::assertInstanceOf(PageInterface::class, $page);

        self::assertRows(EntryChannel::class, ['B2B', 'WEB'], self::channelRows($page));
    }

    /**
     * @test
     */
    public function it_stores_the_overridden_channel_rows_when_the_channels_change(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello', channels: ['WEB']);
        $csrfToken = self::openBuilder($client, $page);

        self::saveSettings($client, $page, ['channels' => ['WEB', 'B2B']], $csrfToken);

        self::assertResponseIsSuccessful();
        self::assertRows(EntryChannel::class, ['B2B', 'WEB'], self::channelRows($page));
    }

    /**
     * @test
     */
    public function it_stores_the_overridden_audience_group_rows_when_the_audience_changes(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello', published: true);
        $csrfToken = self::openBuilder($client, $page);

        self::saveSettings($client, $page, ['audience' => ['mode' => 'only', 'groups' => ['wholesale', '@guest']]], $csrfToken);

        self::assertResponseIsSuccessful();
        self::assertRows(EntryAudienceGroup::class, ['@guest', 'wholesale'], self::audienceGroupRows($page));
    }

    /**
     * @param class-string $class
     * @param list<string> $codes sorted
     * @param list<EntryChannelInterface|EntryAudienceGroupInterface> $rows
     */
    private static function assertRows(string $class, array $codes, array $rows): void
    {
        $actualCodes = [];
        foreach ($rows as $row) {
            self::assertInstanceOf($class, $row);
            $actualCodes[] = $row->getCode();
        }
        sort($actualCodes);

        self::assertSame($codes, $actualCodes);
    }

    /**
     * The page's channel rows as the database holds them
     *
     * @return list<EntryChannelInterface>
     */
    private static function channelRows(PageInterface $page): array
    {
        /** @var RepositoryInterface<EntryChannelInterface> $repository */
        $repository = self::getContainer()->get('setono_sylius_cms.repository.entry_channel');

        return array_values($repository->findBy(['entry' => self::reloadPage($page)]));
    }

    /**
     * The page's audience group rows as the database holds them
     *
     * @return list<EntryAudienceGroupInterface>
     */
    private static function audienceGroupRows(PageInterface $page): array
    {
        /** @var RepositoryInterface<EntryAudienceGroupInterface> $repository */
        $repository = self::getContainer()->get('setono_sylius_cms.repository.entry_audience_group');

        return array_values($repository->findBy(['entry' => self::reloadPage($page)]));
    }

    /**
     * @param array<string, mixed> $settings
     */
    private static function saveSettings(KernelBrowser $client, PageInterface $page, array $settings, string $csrfToken): void
    {
        self::json($client, 'PUT', sprintf('/admin/cms/api/entries/%s/settings', $page->getUuid()), $settings, $csrfToken);
    }
}
