<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api;

use Setono\SyliusCMSPlugin\Tests\Functional\Fixture\SmallUploadLimitKernel;

/**
 * A configured upload limit below PHP's is the effective one: a bigger file is refused with a message naming it, and
 * the builder gets it to check files before uploading them. See "What the library holds" in docs/spec/media.md
 */
final class MediaUploadLimitTest extends MediaApiTestCase
{
    protected static function getKernelClass(): string
    {
        return SmallUploadLimitKernel::class;
    }

    /**
     * @test
     */
    public function it_refuses_a_file_over_the_upload_limit_naming_its_size_and_the_limit(): void
    {
        $client = self::createClient();
        $config = self::openMediaLibrary($client);

        $answer = self::upload($client, $config['csrfToken'], [
            $this->uploadedFile('notes.pdf', "%PDF-1.4\n" . str_repeat('x', 1500) . "\n%%EOF\n"),
            $this->uploadedFile('dot.png', self::png(1, 1)),
        ]);

        self::assertCount(1, $answer['created']);
        self::assertSame('dot', $answer['created'][0]['name']);
        self::assertSame([['filename' => 'notes.pdf', 'error' => 'notes.pdf is 1.5 KB, over the upload limit of 1 KB.']], $answer['refused']);
    }

    /**
     * @test
     */
    public function the_library_and_the_registry_get_the_configured_limit(): void
    {
        $client = self::createClient();
        $config = self::openMediaLibrary($client);

        self::assertSame(1024, $config['media']['maxUploadSize']);
        $registry = self::json($client, 'GET', '/admin/cms/api/registry');
        self::assertIsArray($registry);
        self::assertIsArray($registry['media'] ?? null);
        self::assertSame(1024, $registry['media']['maxUploadSize'] ?? null);
    }
}
