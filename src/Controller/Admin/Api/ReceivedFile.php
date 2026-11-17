<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin\Api;

use Setono\SyliusCMSPlugin\Media\RefusedFileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * An uploaded file the media endpoints take, which PHP received in full
 *
 * @internal
 */
final class ReceivedFile
{
    private function __construct()
    {
    }

    /**
     * @param int $limit the effective upload limit in bytes, which a file PHP refused as too big is over
     *
     * @throws RefusedFileException when PHP didn't receive all of it
     */
    public static function of(UploadedFile $file, int $limit): UploadedFile
    {
        return match ($file->getError()) {
            \UPLOAD_ERR_OK => $file->isValid() ? $file : throw RefusedFileException::notUploaded($file->getClientOriginalName()),
            \UPLOAD_ERR_INI_SIZE, \UPLOAD_ERR_FORM_SIZE => throw RefusedFileException::tooBig($file->getClientOriginalName(), null, $limit),
            default => throw RefusedFileException::notUploaded($file->getClientOriginalName()),
        };
    }
}
