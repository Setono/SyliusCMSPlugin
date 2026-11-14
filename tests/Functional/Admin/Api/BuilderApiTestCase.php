<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api;

use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class BuilderApiTestCase extends WebTestCase
{
    use Entries;

    /**
     * Signs the admin user in and opens the builder, which is where the builder app gets its CSRF token from
     *
     * @return string the CSRF token
     */
    protected static function openBuilder(KernelBrowser $client, PageInterface $page): string
    {
        $client->loginUser(self::admin(), 'admin');
        $crawler = $client->request('GET', sprintf('/admin/cms/entry/%s/builder', $page->getUuid()));

        /** @var array{csrfToken: string} $config */
        $config = json_decode((string) $crawler->filter('#cms-builder')->attr('data-config'), true, 512, \JSON_THROW_ON_ERROR);

        return $config['csrfToken'];
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
