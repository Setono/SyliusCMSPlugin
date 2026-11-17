<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Media;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Media\DeleteReplacedFile;
use Setono\SyliusCMSPlugin\Media\DeleteReplacedFileHandler;
use Setono\SyliusCMSPlugin\Media\MediaFileRemoverInterface;
use Setono\SyliusCMSPlugin\Model\MediaItem;
use Setono\SyliusCMSPlugin\Repository\MediaItemRepositoryInterface;

final class DeleteReplacedFileHandlerTest extends TestCase
{
    use ProphecyTrait;

    private const UUID = '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0a';

    private const PATH = self::UUID . '/3f9a1c2b7d4e5a60/summer.png';

    /**
     * @test
     */
    public function it_deletes_the_old_file_and_its_variants(): void
    {
        $mediaItems = $this->prophesize(MediaItemRepositoryInterface::class);
        $mediaItems->findOneByPath(self::PATH)->willReturn(null);
        $remover = $this->prophesize(MediaFileRemoverInterface::class);

        (new DeleteReplacedFileHandler($mediaItems->reveal(), $remover->reveal()))(new DeleteReplacedFile(self::UUID, self::PATH));

        $remover->remove(self::PATH)->shouldHaveBeenCalledOnce();
    }

    /**
     * Such as when the replace ran in a project's own transaction, which rolled back after the message was sent
     *
     * @test
     */
    public function it_keeps_a_file_that_a_media_item_has_again(): void
    {
        $mediaItems = $this->prophesize(MediaItemRepositoryInterface::class);
        $mediaItems->findOneByPath(self::PATH)->willReturn(new MediaItem());
        $remover = $this->prophesize(MediaFileRemoverInterface::class);

        (new DeleteReplacedFileHandler($mediaItems->reveal(), $remover->reveal()))(new DeleteReplacedFile(self::UUID, self::PATH));

        $remover->remove(Argument::any())->shouldNotHaveBeenCalled();
    }
}
