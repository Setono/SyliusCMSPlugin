<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Transfer\Import;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Transfer\Import\DirectorySource;
use Setono\SyliusCMSPlugin\Transfer\Import\ExportTooBigException;

/**
 * A folder export is untrusted input, so it refuses the paths a zip export refuses (ExportPath::isInsideRoot()),
 * backslashes included, and reads each JSON file no further than one byte past the cap a zip's are read to. See "Media
 * files" in docs/spec/transfer.md
 */
final class DirectorySourceTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/setono-cms-directory-source-' . bin2hex(random_bytes(4));
        mkdir($this->root . '/export/entries', 0777, true);
        file_put_contents($this->root . '/export/entries/page.json', '{}');
        file_put_contents($this->root . '/secret.json', '{}');
    }

    protected function tearDown(): void
    {
        unlink($this->root . '/export/entries/page.json');
        unlink($this->root . '/secret.json');
        rmdir($this->root . '/export/entries');
        rmdir($this->root . '/export');
        rmdir($this->root);
    }

    /**
     * @test
     */
    public function it_has_a_file_inside_its_folder(): void
    {
        self::assertTrue((new DirectorySource($this->root . '/export'))->has('entries/page.json'));
    }

    /**
     * @test
     */
    public function it_refuses_a_path_outside_its_folder_with_slashes_or_backslashes_or_from_the_root(): void
    {
        $source = new DirectorySource($this->root . '/export');

        foreach (['../secret.json', 'entries/../../secret.json', '..\\secret.json', 'entries\\..\\..\\secret.json', '/etc/passwd', '\\secret.json', '/entries/page.json', '\\entries\\page.json'] as $path) {
            self::assertFalse($source->has($path), $path);
        }
    }

    /**
     * @test
     */
    public function it_reads_a_json_file_of_exactly_the_cap(): void
    {
        file_put_contents($this->root . '/export/entries/page.json', str_repeat(' ', 1024));

        self::assertSame(str_repeat(' ', 1024), (new DirectorySource($this->root . '/export', 1024))->read('entries/page.json'));
    }

    /**
     * @test
     */
    public function it_refuses_a_json_file_over_the_cap_naming_it(): void
    {
        file_put_contents($this->root . '/export/entries/page.json', str_repeat(' ', 1025));

        try {
            (new DirectorySource($this->root . '/export', 1024))->read('entries/page.json');
            self::fail('It read a JSON file over the cap');
        } catch (ExportTooBigException $e) {
            self::assertSame('setono_sylius_cms.import.json_file.over_limit', $e->reason->getMessage());
            self::assertSame(['%path%' => 'entries/page.json', '%limit%' => '1 KB'], $e->reason->getParameters());
        }
    }
}
