<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin;

use League\Flysystem\FilesystemOperator;
use Setono\SyliusCMSPlugin\Model\ImportInterface;
use Setono\SyliusCMSPlugin\Repository\ImportRepositoryInterface;
use Setono\SyliusCMSPlugin\Tests\Functional\Fixture\SmallImportLimitsKernel;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tests\Support\Imports;
use Setono\SyliusCMSPlugin\Tests\Support\MediaItems;
use Setono\SyliusCMSPlugin\Tests\Support\SentMessages;
use Setono\SyliusCMSPlugin\Tests\Support\TemporaryDirectory;
use Setono\SyliusCMSPlugin\Transfer\Import\ImportRefusal;
use Setono\SyliusCMSPlugin\Transfer\Import\ImportResult;
use Setono\SyliusCMSPlugin\Transfer\Import\ImportResultNormalizer;
use Setono\SyliusCMSPlugin\Transfer\Import\ImportState;
use Setono\SyliusCMSPlugin\Transfer\Import\ImportStatus;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Field\FileFormField;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * An import started in the admin takes media files of up to transfer.max_file_size, 1 KB here, and zips unpacking to up
 * to transfer.max_unpacked_size, 16 KB here. Its dry run on the Messenger worker refuses one over either, and the
 * import's page shows why. See "Media files" and "Who runs it" in docs/spec/transfer.md
 */
final class ImportLimitsTest extends WebTestCase
{
    use Entries;
    use Imports;
    use MediaItems;
    use SentMessages;
    use TemporaryDirectory;

    /** A video in Unsorted, of 28 bytes */
    private const VIDEO = '0193a5be-70ab-7c12-8e2d-1b2c3d4e5f61';

    /** What the committed export's files add up to, in bytes */
    private const EXPORT_SIZE = 9583;

    protected static function getKernelClass(): string
    {
        return SmallImportLimitsKernel::class;
    }

    /**
     * @test
     */
    public function it_refuses_a_media_file_over_the_limit_and_stores_why(): void
    {
        $export = $this->copyOfFixture();
        self::growVideo($export, self::VIDEO, 1024 - 28 - 8 + 1);

        [$refusal, $page] = $this->refusalOf($this->zipOf($export));

        self::assertSame(self::VIDEO, $refusal->uuid);
        self::assertInstanceOf(TranslatableMessage::class, $refusal->reason);
        self::assertSame('setono_sylius_cms.import.media_refused', $refusal->reason->getMessage());
        $reason = $refusal->reason->getParameters()['%reason%'] ?? null;
        self::assertInstanceOf(TranslatableMessage::class, $reason);
        self::assertSame('setono_sylius_cms.import.media_file.over_limit', $reason->getMessage());
        // In any order, as the database keeps JSON's keys in its own
        self::assertEquals(['%filename%' => 'lookbook.mp4', '%limit%' => '1 KB'], $reason->getParameters());
        self::assertStringContainsString('The media item lookbook can\'t be imported: lookbook.mp4 is over the import limit of 1 KB per file.', $page);
    }

    /**
     * @test
     */
    public function it_refuses_a_zip_that_unpacks_to_more_than_the_limit_and_stores_why(): void
    {
        [$refusal, $page] = $this->refusalOf($this->zipOf(self::exportFixture(), ['padding.txt' => str_repeat('a', 20 * 1024 - self::EXPORT_SIZE)]));

        self::assertNull($refusal->uuid);
        self::assertInstanceOf(TranslatableMessage::class, $refusal->reason);
        self::assertSame('setono_sylius_cms.import.zip.too_big', $refusal->reason->getMessage());
        self::assertEquals(['%size%' => '20 KB', '%limit%' => '16 KB'], $refusal->reason->getParameters());
        self::assertStringContainsString('The export zip unpacks to 20 KB, over the import limit of 16 KB.', $page);
    }

    /**
     * Uploads the zip, has the Messenger worker run its dry run, which refuses it for one reason, and finishes it
     *
     * @return array{ImportRefusal, string} the reason stored, and the text of the import's page that shows the result
     */
    private function refusalOf(string $zip): array
    {
        self::forgetSentMessages();
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');

        $form = $client->request('GET', '/admin/cms/transfer')->filter('form[data-cms-import-upload]')->form();
        $field = $form['export'];
        self::assertInstanceOf(FileFormField::class, $field);
        $field->upload($zip);
        $client->submit($form);

        $import = self::latestImport();
        $path = (string) $import->getPath();
        self::assertSame(1, self::consumeMessages());

        $import = self::latestImport();
        self::assertSame(ImportState::Finished, $import->getState());
        self::assertFalse(self::storage()->fileExists($path), 'The zip is deleted once the import finishes');
        $result = self::resultOf($import);
        self::assertSame(ImportStatus::Refused, $result->status);
        self::assertSame(1, $result->exitCode());
        self::assertNotNull($result->plan);
        self::assertCount(1, $result->plan->refusals);

        $crawler = $client->request('GET', sprintf('/admin/cms/transfer/imports/%s', $import->getUuid()));
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('The import refused. Nothing changed.', $crawler->filter('[data-cms-import-result]')->text());

        return [$result->plan->refusals[0], $crawler->filter('[data-cms-import-reasons]')->text()];
    }

    private static function latestImport(): ImportInterface
    {
        self::entityManager()->clear();

        /** @var ImportRepositoryInterface $imports */
        $imports = self::getContainer()->get(ImportRepositoryInterface::class);
        $latest = $imports->findLatest(1);
        self::assertCount(1, $latest);

        return $latest[0];
    }

    private static function resultOf(ImportInterface $import): ImportResult
    {
        return (new ImportResultNormalizer())->denormalize($import->getResult() ?? []);
    }

    private static function storage(): FilesystemOperator
    {
        /** @var FilesystemOperator $storage */
        $storage = self::getContainer()->get('setono_sylius_cms.transfer_storage');

        return $storage;
    }
}
