<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api;

final class RegistryTest extends BuilderApiTestCase
{
    /**
     * @test
     */
    public function it_needs_an_admin_user(): void
    {
        $client = self::createClient();

        $client->request('GET', '/admin/cms/api/registry');

        self::assertResponseRedirects('http://localhost/admin/login');
    }

    /**
     * @test
     */
    public function it_answers_the_block_types_with_their_field_schemas_and_the_ui_strings(): void
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');

        $registry = self::json($client, 'GET', '/admin/cms/api/registry');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertIsArray($registry);
        self::assertIsArray($registry['blockTypes']);

        $heading = $registry['blockTypes'][0];
        self::assertIsArray($heading);
        self::assertSame('heading', $heading['code']);
        self::assertSame('Heading', $heading['label']);
        self::assertSame(1, $heading['version']);
        self::assertSame([
            [
                'name' => 'text',
                'schema' => [
                    'type' => 'string',
                    'x-cms-options' => ['maxLength' => null],
                    'x-cms-type' => 'text',
                    'x-cms-label' => 'Text',
                    'x-cms-help' => null,
                    'x-cms-required' => true,
                    'x-cms-translatable' => true,
                ],
            ],
            [
                'name' => 'level',
                'schema' => [
                    'type' => 'string',
                    'enum' => ['h1', 'h2', 'h3', 'h4'],
                    'x-cms-options' => [
                        'choices' => ['h1' => 'Heading 1', 'h2' => 'Heading 2', 'h3' => 'Heading 3', 'h4' => 'Heading 4'],
                        'multiple' => false,
                    ],
                    'default' => 'h2',
                    'x-cms-type' => 'choice',
                    'x-cms-label' => 'Level',
                    'x-cms-help' => null,
                    'x-cms-required' => false,
                    'x-cms-translatable' => false,
                ],
            ],
        ], $heading['fields']);

        self::assertIsArray($registry['strings']);
        self::assertSame('Saving…', $registry['strings']['setono_sylius_cms.builder.saving'] ?? null);
        self::assertSame('Saved', $registry['strings']['setono_sylius_cms.builder.saved'] ?? null);
        self::assertSame('Publish', $registry['strings']['setono_sylius_cms.builder.publish'] ?? null);
    }
}
