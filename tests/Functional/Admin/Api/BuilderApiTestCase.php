<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\User\UserInterface;

abstract class BuilderApiTestCase extends WebTestCase
{
    use Entries;

    /**
     * A client signed in as the admin user
     */
    protected static function signedIn(): KernelBrowser
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');

        return $client;
    }

    /**
     * Signs the admin user in, "sylius" unless another is given, and opens the builder, which is where the builder app
     * gets its CSRF token from. Signing in starts a new session, so the CSRF token of an earlier one no longer works
     *
     * @return string the CSRF token
     */
    protected static function openBuilder(KernelBrowser $client, EntryInterface $page, ?UserInterface $admin = null): string
    {
        $client->loginUser($admin ?? self::admin(), 'admin');
        $crawler = $client->request('GET', sprintf('/admin/cms/entry/%s/builder', $page->getUuid()));

        /** @var array{csrfToken: string} $config */
        $config = json_decode((string) $crawler->filter('#cms-builder')->attr('data-config'), true, 512, \JSON_THROW_ON_ERROR);

        return $config['csrfToken'];
    }

    /**
     * The lock token entry_show answers with, which a save carries
     */
    protected static function lockToken(KernelBrowser $client, PageInterface $page): int
    {
        $response = self::json($client, 'GET', '/admin/cms/api/entries/' . $page->getUuid());
        self::assertIsArray($response);
        self::assertIsInt($response['lockToken'] ?? null);

        return $response['lockToken'];
    }

    /**
     * The presets preset_index answers with at a spot, by id
     *
     * @return array<string, array{id: string, fromCode: bool, name: string, category: string|null, description: string|null, thumbnail: string|null, fits: bool, reasons: list<string>, issues: list<string>, blocks: list<array<string, mixed>>}>
     */
    protected static function presetsAt(KernelBrowser $client, string $uri): array
    {
        $response = self::json($client, 'GET', $uri);
        self::assertResponseIsSuccessful();
        self::assertIsArray($response);
        self::assertIsArray($response['presets'] ?? null);

        $presets = [];
        foreach ($response['presets'] as $preset) {
            self::assertIsArray($preset);
            self::assertIsString($preset['id'] ?? null);
            /** @var array{id: string, fromCode: bool, name: string, category: string|null, description: string|null, thumbnail: string|null, fits: bool, reasons: list<string>, issues: list<string>, blocks: list<array<string, mixed>>} $listed */
            $listed = $preset;
            $presets[$preset['id']] = $listed;
        }

        return $presets;
    }

    /**
     * @param array<array-key, mixed>|null $body
     */
    protected static function json(KernelBrowser $client, string $method, string $uri, ?array $body = null, ?string $csrfToken = null): mixed
    {
        $server = ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'];
        if (null !== $csrfToken) {
            $server['HTTP_X_CSRF_TOKEN'] = $csrfToken;
        }

        $client->request($method, $uri, [], [], $server, null === $body ? null : json_encode($body, \JSON_THROW_ON_ERROR));

        return json_decode((string) $client->getResponse()->getContent(), true);
    }
}
