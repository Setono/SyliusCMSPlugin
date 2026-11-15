<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api;

use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Admins;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * Several editors on one entry: the lock token, merging saves, clashes and presence. See "Saving" in
 * docs/spec/publishing.md and "JSON endpoints" in docs/spec/builder.md
 */
final class ConcurrentEditingTest extends BuilderApiTestCase
{
    use Admins;
    use ClockSensitiveTrait;

    private const A = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4a';

    private const B = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4b';

    /**
     * @test
     *
     * @dataProvider provideStates
     */
    public function each_save_moves_the_lock_token_on(bool $published): void
    {
        $client = self::createClient();
        $page = self::createTwoHeadings($published);
        $csrfToken = self::openBuilder($client, $page);

        self::assertSame(0, self::lockToken($client, $page));

        $response = self::save($client, $page, 0, [self::setText(self::A, 'Hello there')], $csrfToken);

        self::assertResponseIsSuccessful();
        self::assertSame(['status' => $published ? 'unpublished_changes' : 'never_published', 'lockToken' => 1], $response);
        self::assertSame(1, self::lockToken($client, $page));
    }

    /**
     * @test
     *
     * @dataProvider provideStates
     */
    public function two_saves_with_the_same_old_lock_token_merge_and_the_second_gets_the_first_ones_changes(bool $published): void
    {
        $client = self::createClient();
        $page = self::createTwoHeadings($published);
        $jane = self::createAdmin('jane', 'Jane', 'Roe');

        $csrfToken = self::openBuilder($client, $page);
        self::save($client, $page, 0, [self::setText(self::A, 'Hello there')], $csrfToken);

        $csrfToken = self::openBuilder($client, $page, $jane);
        $response = self::save($client, $page, 0, [self::setText(self::B, 'Wide world')], $csrfToken);

        self::assertResponseIsSuccessful();
        self::assertIsArray($response);
        self::assertSame(2, $response['lockToken'] ?? null);
        self::assertSame([], $response['clashes'] ?? null);
        self::assertSame(['Hello there', 'Wide world'], self::texts($response['entry'] ?? null));

        $client->request('GET', '/admin/cms/api/entries/' . $page->getUuid());
        self::assertSame(['Hello there', 'Wide world'], self::texts(self::responseData($client)['entry'] ?? null));
    }

    /**
     * @test
     */
    public function it_answers_a_save_with_the_latest_lock_token_without_the_working_copy(): void
    {
        $client = self::createClient();
        $page = self::createTwoHeadings(false);
        $csrfToken = self::openBuilder($client, $page);

        self::save($client, $page, 0, [self::setText(self::A, 'Hello there')], $csrfToken);
        $response = self::save($client, $page, 1, [self::setText(self::B, 'Wide world')], $csrfToken);

        self::assertSame(['status' => 'never_published', 'lockToken' => 2], $response);
    }

    /**
     * @test
     */
    public function it_answers_an_empty_save_with_an_old_lock_token_with_what_colleagues_saved(): void
    {
        $client = self::createClient();
        $page = self::createTwoHeadings(true);
        $csrfToken = self::openBuilder($client, $page);

        self::save($client, $page, 0, [self::setText(self::A, 'Hello there')], $csrfToken);
        $response = self::save($client, $page, 0, [], $csrfToken);

        self::assertIsArray($response);
        self::assertSame(1, $response['lockToken'] ?? null);
        self::assertSame(['Hello there', 'World'], self::texts($response['entry'] ?? null));
        self::assertSame(1, self::lockToken($client, $page));
    }

    /**
     * @test
     */
    public function it_answers_a_clash_with_its_details_and_saves_nothing_of_it(): void
    {
        $client = self::createClient();
        $page = self::createTwoHeadings(false);
        $csrfToken = self::openBuilder($client, $page);

        self::save($client, $page, 0, [self::setText(self::A, 'Theirs')], $csrfToken);
        $response = self::save($client, $page, 0, [self::setText(self::A, 'Mine'), self::setText(self::B, 'Wide world')], $csrfToken);

        self::assertResponseIsSuccessful();
        self::assertIsArray($response);
        self::assertSame(2, $response['lockToken'] ?? null);
        self::assertSame(
            [['kind' => 'value', 'key' => self::A, 'field' => 'text', 'locale' => 'en_US', 'mine' => 'Mine', 'theirs' => 'Theirs']],
            $response['clashes'] ?? null,
        );
        self::assertSame(['Theirs', 'Wide world'], self::texts($response['entry'] ?? null));
        self::assertSame(['Theirs', 'Wide world'], self::texts(self::workingCopy($client, $page)));
    }

    /**
     * @test
     */
    public function it_answers_an_edit_to_a_block_a_colleague_deleted_as_a_clash(): void
    {
        $client = self::createClient();
        $page = self::createTwoHeadings(true);
        $csrfToken = self::openBuilder($client, $page);

        self::save($client, $page, 0, [['op' => 'remove_block', 'key' => self::B]], $csrfToken);
        $response = self::save($client, $page, 0, [self::setText(self::B, 'Wide world')], $csrfToken);

        self::assertIsArray($response);
        self::assertSame(1, $response['lockToken'] ?? null);
        self::assertSame([['kind' => 'deleted_block', 'key' => self::B]], $response['clashes'] ?? null);
        self::assertSame(['Hello'], self::texts($response['entry'] ?? null));
    }

    /**
     * @test
     */
    public function it_moves_the_lock_token_on_with_a_discard_so_a_colleague_pulls_in_the_published_content_with_their_edit(): void
    {
        $client = self::createClient();
        $page = self::createTwoHeadings(true);
        $jane = self::createAdmin('jane', 'Jane', 'Roe');

        $johnsToken = self::openBuilder($client, $page);
        self::save($client, $page, 0, [self::setText(self::A, 'Hello there')], $johnsToken);

        $discarded = self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/discard', $page->getUuid()), null, $johnsToken);
        self::assertIsArray($discarded);
        self::assertSame(2, $discarded['lockToken'] ?? null);

        // Jane loaded the draft at lock token 1, before the discard
        $janesToken = self::openBuilder($client, $page, $jane);
        self::assertSame(['editors' => [], 'lockToken' => 2], self::presence($client, $page, $janesToken));

        $response = self::save($client, $page, 1, [self::setText(self::B, 'Wide world')], $janesToken);

        self::assertResponseIsSuccessful();
        self::assertIsArray($response);
        self::assertSame(3, $response['lockToken'] ?? null);
        self::assertSame([], $response['clashes'] ?? null);
        self::assertSame(['Hello', 'Wide world'], self::texts($response['entry'] ?? null));
        self::assertSame(['Hello', 'Wide world'], self::texts(self::workingCopy($client, $page)));
    }

    /**
     * @test
     */
    public function it_answers_an_edit_to_a_block_only_the_discarded_draft_had_as_a_clash(): void
    {
        $client = self::createClient();
        $page = self::createTwoHeadings(true);
        $jane = self::createAdmin('jane', 'Jane', 'Roe');
        $added = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4c';

        $johnsToken = self::openBuilder($client, $page);
        self::save($client, $page, 0, [['op' => 'add_block', 'parent' => null, 'index' => 2, 'after' => self::B, 'block' => ['key' => $added, 'type' => 'heading', 'version' => 1]]], $johnsToken);
        self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/discard', $page->getUuid()), null, $johnsToken);

        // Jane loaded the draft at lock token 1, with the added heading, before the discard
        $janesToken = self::openBuilder($client, $page, $jane);
        $response = self::save($client, $page, 1, [self::setText($added, 'Added and edited')], $janesToken);

        self::assertResponseIsSuccessful();
        self::assertIsArray($response);
        self::assertSame(2, $response['lockToken'] ?? null);
        self::assertSame([['kind' => 'deleted_block', 'key' => $added]], $response['clashes'] ?? null);
        self::assertSame(['Hello', 'World'], self::texts($response['entry'] ?? null));
    }

    /**
     * @test
     */
    public function it_leaves_the_lock_token_when_discarding_an_entry_without_a_draft(): void
    {
        $client = self::createClient();
        $page = self::createTwoHeadings(true);
        $csrfToken = self::openBuilder($client, $page);

        $discarded = self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/discard', $page->getUuid()), null, $csrfToken);

        self::assertIsArray($discarded);
        self::assertSame(0, $discarded['lockToken'] ?? null);
    }

    /**
     * @test
     */
    public function it_needs_a_lock_token_to_save(): void
    {
        $client = self::createClient();
        $page = self::createTwoHeadings(false);
        $csrfToken = self::openBuilder($client, $page);

        foreach ([null, -1, '1'] as $lockToken) {
            $body = ['changes' => [self::setText(self::A, 'Hello there')]];
            if (null !== $lockToken) {
                $body['lockToken'] = $lockToken;
            }

            $response = self::json($client, 'PATCH', '/admin/cms/api/entries/' . $page->getUuid(), $body, $csrfToken);

            self::assertResponseStatusCodeSame(400);
            self::assertIsArray($response);
            self::assertIsString($response['error'] ?? null);
        }

        self::assertSame(['Hello', 'World'], self::texts(self::workingCopy($client, $page)));
    }

    /**
     * @test
     */
    public function it_refuses_a_lock_token_the_server_never_gave(): void
    {
        $client = self::createClient();
        $page = self::createTwoHeadings(false);
        $csrfToken = self::openBuilder($client, $page);

        $response = self::save($client, $page, 1, [self::setText(self::A, 'Hello there')], $csrfToken);

        self::assertResponseStatusCodeSame(409);
        self::assertIsArray($response);
        self::assertIsString($response['error'] ?? null);
        self::assertSame(['Hello', 'World'], self::texts(self::workingCopy($client, $page)));
    }

    /**
     * @test
     */
    public function it_lists_the_other_admin_users_who_have_the_entry_open_in_presence(): void
    {
        $clock = self::mockTime('2026-11-14 10:00:00');
        $client = self::createClient();
        $page = self::createTwoHeadings(false);
        $jane = self::createAdmin('jane', 'Jane', 'Roe');

        $johnsToken = self::openBuilder($client, $page);
        self::assertSame(['editors' => [], 'lockToken' => 0], self::presence($client, $page, $johnsToken));

        $janesToken = self::openBuilder($client, $page, $jane);
        self::assertSame(['editors' => [['name' => 'John Doe']], 'lockToken' => 0], self::presence($client, $page, $janesToken));
        self::assertSame([['name' => 'John Doe']], self::responseData($client, 'GET', '/admin/cms/api/entries/' . $page->getUuid())['editors'] ?? null);

        $clock->sleep(10);
        $johnsToken = self::openBuilder($client, $page);
        self::save($client, $page, 0, [self::setText(self::A, 'Hello there')], $johnsToken);
        self::assertSame(['editors' => [['name' => 'Jane Roe']], 'lockToken' => 1], self::presence($client, $page, $johnsToken));

        // Jane stopped sending it
        $clock->sleep(25);
        self::assertSame(['editors' => [], 'lockToken' => 1], self::presence($client, $page, $johnsToken));
    }

    /**
     * @test
     */
    public function it_needs_an_admin_user_and_a_valid_csrf_token_for_presence(): void
    {
        $client = self::createClient();
        $page = self::createTwoHeadings(false);

        self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/presence', $page->getUuid()));
        self::assertResponseRedirects('http://localhost/admin/login');

        self::openBuilder($client, $page);
        self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/presence', $page->getUuid()), null, 'not-the-token');
        self::assertResponseStatusCodeSame(403);
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function provideStates(): iterable
    {
        yield 'a never-published entry\'s rows' => [false];
        yield 'a draft' => [true];
    }

    private static function createTwoHeadings(bool $published): PageInterface
    {
        return self::createPageWithBlocks('About us', 'about-us', [self::heading(self::A, 'Hello'), self::heading(self::B, 'World')], $published);
    }

    /**
     * @return array{op: string, key: string, field: string, locale: string, value: string}
     */
    private static function setText(string $key, string $text): array
    {
        return ['op' => 'set_value', 'key' => $key, 'field' => 'text', 'locale' => 'en_US', 'value' => $text];
    }

    /**
     * @param list<array<string, mixed>> $changes
     */
    private static function save(KernelBrowser $client, PageInterface $page, int $lockToken, array $changes, string $csrfToken): mixed
    {
        return self::json($client, 'PATCH', '/admin/cms/api/entries/' . $page->getUuid(), ['lockToken' => $lockToken, 'changes' => $changes], $csrfToken);
    }

    private static function presence(KernelBrowser $client, PageInterface $page, string $csrfToken): mixed
    {
        $response = self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/presence', $page->getUuid()), null, $csrfToken);
        self::assertResponseIsSuccessful();

        return $response;
    }

    private static function workingCopy(KernelBrowser $client, PageInterface $page): mixed
    {
        return self::responseData($client, 'GET', '/admin/cms/api/entries/' . $page->getUuid())['entry'] ?? null;
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function responseData(KernelBrowser $client, ?string $method = null, ?string $uri = null): array
    {
        if (null !== $method && null !== $uri) {
            $client->request($method, $uri);
        }

        $data = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($data);

        return $data;
    }

    /**
     * The headings' texts in en_US, in tree order, from an entry in the entry format
     *
     * @return list<mixed>
     */
    private static function texts(mixed $entry): array
    {
        self::assertIsArray($entry);
        self::assertIsArray($entry['tree'] ?? null);
        self::assertIsArray($entry['tree']['blocks'] ?? null);

        return array_values(array_map(static function (mixed $block): mixed {
            self::assertIsArray($block);
            self::assertIsArray($block['fields'] ?? null);
            self::assertIsArray($block['fields']['text'] ?? null);

            return $block['fields']['text']['en_US'] ?? null;
        }, $entry['tree']['blocks']));
    }
}
