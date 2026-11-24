<?php

declare(strict_types=1);

namespace Eshimischi\VendorCleanup\Tests;

use Eshimischi\VendorCleanup\Cleaner;
use Eshimischi\VendorCleanup\CleanupConfig;
use PHPUnit\Framework\TestCase;
use Random\RandomException;

final class CleanerTest extends TestCase
{
    private string $tempDir;

    /**
     * @throws RandomException
     */
    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/vendor_cleanup_test_' . bin2hex(random_bytes(6));
        mkdir($this->tempDir . '/pkg/src', 0777, true);
        mkdir($this->tempDir . '/pkg/tests', 0777, true);
        mkdir($this->tempDir . '/pkg/docs', 0777, true);

        file_put_contents($this->tempDir . '/pkg/src/Service.php', '<?php');
        file_put_contents($this->tempDir . '/pkg/tests/ServiceTest.php', '<?php');
        file_put_contents($this->tempDir . '/pkg/docs/guide.md', '# Guide');
        file_put_contents($this->tempDir . '/pkg/README.md', '# Readme');
        file_put_contents($this->tempDir . '/pkg/.travis.yml', 'language: php');
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->tempDir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->tempDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        /** @var \SplFileInfo $item */
        foreach ($iterator as $item) {
            if ($item->isLink() || !$item->isDir()) {
                @unlink($item->getPathname());
            } else {
                @rmdir($item->getPathname());
            }
        }

        @rmdir($this->tempDir);
    }

    public function testDryRunDoesNotDeleteFiles(): void
    {
        $config = CleanupConfig::create(targetPath: $this->tempDir, dryRun: true);
        $matched = [];

        $count = (new Cleaner())->run($config, static function (string $path) use (&$matched): void {
            $matched[] = basename($path);
        });

        self::assertGreaterThanOrEqual(4, $count);
        self::assertFileExists($this->tempDir . '/pkg/tests/ServiceTest.php');
        self::assertFileExists($this->tempDir . '/pkg/README.md');
    }

    public function testRemovesJunkAndKeepsProductionCode(): void
    {
        $config = CleanupConfig::create(targetPath: $this->tempDir, exclude: ['docs']);

        new Cleaner()->run($config, static function (string $path): void {});

        self::assertFileExists($this->tempDir . '/pkg/src/Service.php');
        self::assertDirectoryDoesNotExist($this->tempDir . '/pkg/tests');
        self::assertFileDoesNotExist($this->tempDir . '/pkg/README.md');
        self::assertFileDoesNotExist($this->tempDir . '/pkg/.travis.yml');
        self::assertDirectoryExists($this->tempDir . '/pkg/docs');
    }

    public function testSymlinkIsUnlinkedWithoutDeletingTargetContent(): void
    {
        $externalDir = $this->tempDir . '_external';
        mkdir($externalDir, 0777, true);
        $importantFile = $externalDir . '/important.php';
        file_put_contents($importantFile, '<?php');

        symlink($externalDir, $this->tempDir . '/pkg/tests-link');

        $config = CleanupConfig::create(targetPath: $this->tempDir, include: ['tests-link']);
        (new Cleaner())->run($config, static function (string $path): void {});

        self::assertFalse(is_link($this->tempDir . '/pkg/tests-link'));
        self::assertFileExists($importantFile);

        @unlink($importantFile);
        @rmdir($externalDir);
    }
}
