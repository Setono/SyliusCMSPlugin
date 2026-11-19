<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer\Import;

use Doctrine\Persistence\ObjectManager;
use Psr\Clock\ClockInterface;
use Setono\SyliusCMSPlugin\Model\ImportInterface;
use Setono\SyliusCMSPlugin\Transfer\Format\ExportFormat;
use Setono\SyliusCMSPlugin\Transfer\Format\InvalidExportException;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

final readonly class ImportQueue implements ImportQueueInterface
{
    /**
     * @param FactoryInterface<ImportInterface> $importFactory
     * @param ObjectManager $manager the import resource's manager
     * @param MessageBusInterface $messageBus the bus the messages go on, Sylius's event bus, which has no transaction of
     *                                        its own around the handler, as the import has its own
     */
    public function __construct(
        private UploadedExports $uploadedExports,
        private FactoryInterface $importFactory,
        private ObjectManager $manager,
        private MessageBusInterface $messageBus,
        private ClockInterface $clock,
    ) {
    }

    public function upload(\SplFileInfo $file, string $filename): ImportInterface
    {
        // Only what looks like an export is kept: the worker checks the rest
        if (!(new ZipSource($file->getPathname()))->has(ExportFormat::MANIFEST)) {
            throw new InvalidExportException(sprintf('%s has no %s, so it isn\'t an export', $filename, ExportFormat::MANIFEST));
        }

        $import = $this->importFactory->createNew();
        $import->setFilename($filename);
        $import->setPath($this->uploadedExports->keep($file));
        $import->setCreatedAt($this->clock->now());
        $this->manager->persist($import);
        $this->manager->flush();

        // Sent once the import is saved, so the worker finds it
        $this->messageBus->dispatch(new RunImport($import->getUuid()));
        $this->messageBus->dispatch(new AbandonImport($import->getUuid()), [DelayStamp::delayFor(new \DateInterval(self::ABANDONED_AFTER))]);

        return $import;
    }

    public function confirm(ImportInterface $import, bool $overwrite, bool $publish): void
    {
        $import->confirm($overwrite, $publish);
        $this->manager->flush();

        $this->messageBus->dispatch(new RunImport($import->getUuid()));
    }

    public function abandon(ImportInterface $import): void
    {
        $path = $import->getPath();
        $import->abandon($this->clock->now());
        $this->manager->flush();

        if (null !== $path) {
            $this->uploadedExports->delete($path);
        }
    }
}
