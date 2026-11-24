<?php

declare(strict_types=1);

namespace Eshimischi\VendorCleanup\Tests;

use Eshimischi\VendorCleanup\VendorLocator;
use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use Random\RandomException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

final class VendorLocatorTest extends TestCase
{
    private string $tempProject;
    private string $originalCwd;

    /**
     * @throws RandomException
     */
    #[\Override]
    protected function setUp(): void
    {
        $cwd = getcwd();
        self::assertNotFalse($cwd);
        $this->originalCwd = $cwd;

        $this->tempProject = sys_get_temp_dir() . '/vendor_locator_test_' . bin2hex(random_bytes(6));
        mkdir($this->tempProject . '/vendor/symfony', 0777, true);
        mkdir($this->tempProject . '/modules/dalee.core', 0777, true);
        file_put_contents($this->tempProject . '/composer.json', '{}');

        chdir($this->tempProject);
    }

    #[\Override]
    protected function tearDown(): void
    {
        chdir($this->originalCwd);

        if (!is_dir($this->tempProject)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->tempProject, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
            if ($item->isLink() || !$item->isDir()) {
                @unlink($item->getPathname());
            } else {
                @rmdir($item->getPathname());
            }
        }

        @rmdir($this->tempProject);
    }

    public function testResolvesCustomDirectoryRelativeToProjectRoot(): void
    {
        $resolved = new VendorLocator()->resolve(customDir: 'modules/dalee.core');

        self::assertSame(realpath($this->tempProject . '/modules/dalee.core'), $resolved);
    }

    public function testResolvesVendorSubdirectoryByDefault(): void
    {
        $resolved = new VendorLocator()->resolve(subPath: 'symfony');

        self::assertSame(realpath($this->tempProject . '/vendor/symfony'), $resolved);
    }

    public function testBlocksPathTraversalOutsideProjectRoot(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Path traversal outside of project root is forbidden.');

        new VendorLocator()->resolve(customDir: '..');
    }

    public function testBlocksPathTraversalOutsideVendorDirectory(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Path traversal outside of vendor directory is forbidden.');

        new VendorLocator()->resolve(subPath: '../modules');
    }
}
