<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin;

use Doctrine\DBAL\Connection;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyManagerInterface;
use Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api\BuilderApiTestCase;
use Setono\SyliusCMSPlugin\Tests\Support\ScheduledPublishes;
use Setono\SyliusCMSPlugin\Tests\Support\ServerTimeZone;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * A scheduled publish is entered and shown in PHP's default time zone, which the admin shows times in, on the overview
 * page and in the builder alike, labelled with its name, and stored in UTC. See "Scheduled publishes" in
 * docs/spec/publishing.md
 */
final class SchedulingTimeZoneTest extends BuilderApiTestCase
{
    use ClockSensitiveTrait;
    use ScheduledPublishes;
    use ServerTimeZone;

    protected function setUp(): void
    {
        $this->useServerTimeZone('Europe/Copenhagen');
        self::mockTime('2026-11-20 10:00:00');
        self::forgetSentMessages();
    }

    /**
     * @test
     */
    public function it_stores_the_same_utc_time_for_a_time_entered_on_the_overview_page_and_in_the_builder(): void
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');
        $fromOverview = self::pageWithDraft('About us', 'about-us');
        $fromBuilder = self::pageWithDraft('Opening hours', 'opening-hours');

        // On the overview page, the form says which time zone the time is entered in
        $crawler = $client->request('GET', sprintf('/admin/cms/entry/%s', $fromOverview->getUuid()));
        $form = $crawler->filter('[data-schedule-form]');
        self::assertSame('Europe/Copenhagen', $form->filter('#' . (string) $form->filter('input[name="at"]')->attr('aria-describedby'))->text());
        $client->submitForm('Schedule', ['at' => '2026-11-20T11:30']);
        self::assertResponseRedirects();

        // The builder converts the time entered with the time zone the server gives it, and sends it in UTC
        $config = self::builderConfig($client, $fromBuilder);
        $entered = new \DateTimeImmutable('2026-11-20T11:30', new \DateTimeZone($config['timeZone']));
        self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/schedule', $fromBuilder->getUuid()), [
            'at' => $entered->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
        ], $config['csrfToken']);
        self::assertResponseIsSuccessful();

        // 11:30 in Copenhagen is 10:30 in UTC, which the column holds
        foreach ([$fromOverview, $fromBuilder] as $entry) {
            self::assertSame('2026-11-20T10:30:00+00:00', self::scheduledPublishOf($entry)?->getPublishAt()->format(\DATE_ATOM));
            self::assertSame('2026-11-20 10:30:00', self::publishAtColumn($entry));
        }
    }

    /**
     * @test
     */
    public function it_shows_a_scheduled_time_on_the_overview_page_in_php_s_default_time_zone_with_its_name(): void
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');
        $page = self::pageWithDraft();
        $overview = sprintf('/admin/cms/entry/%s', $page->getUuid());

        $client->request('GET', $overview);
        $client->submitForm('Schedule', ['at' => '2026-11-20T11:30']);
        $crawler = $client->followRedirect();

        self::assertSelectorTextContains('.sylius-flash-message', 'The publish has been scheduled for 2026-11-20 11:30 (Europe/Copenhagen).');
        self::assertSame('Publishes 2026-11-20 11:30 (Europe/Copenhagen)', $crawler->filter('[data-section="publishing"] [data-scheduled-publish] .header')->text());
    }

    /**
     * A published page, with a draft that changes its title
     */
    private static function pageWithDraft(string $title = 'About us', string $slug = 'about-us'): PageInterface
    {
        $page = self::createPage($title, $slug, 'Hello', published: true);

        /** @var WorkingCopyManagerInterface $workingCopies */
        $workingCopies = self::getContainer()->get(WorkingCopyManagerInterface::class);
        $workingCopy = $workingCopies->getWorkingCopy($page);
        $workingCopy->setTitle($title . ' and more');
        $workingCopies->save($page, $workingCopy);

        return $page;
    }

    /**
     * The config the builder page gives the builder app
     *
     * @return array{csrfToken: string, timeZone: string}
     */
    private static function builderConfig(KernelBrowser $client, EntryInterface $entry): array
    {
        $crawler = $client->request('GET', sprintf('/admin/cms/entry/%s/builder', $entry->getUuid()));

        /** @var array{csrfToken: string, timeZone: string} $config */
        $config = json_decode((string) $crawler->filter('#cms-builder')->attr('data-config'), true, 512, \JSON_THROW_ON_ERROR);

        return $config;
    }

    /**
     * The entry's scheduled publish's time as its column holds it
     */
    private static function publishAtColumn(EntryInterface $entry): mixed
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');

        return $connection->fetchOne('SELECT publish_at FROM setono_sylius_cms__scheduled_publish WHERE entry_id = ?', [$entry->getId()]);
    }
}
