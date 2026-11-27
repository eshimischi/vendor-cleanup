<?php

declare(strict_types=1);

namespace Eshimischi\VendorCleanup\Tests;

use Eshimischi\VendorCleanup\IgnoreList;
use PHPUnit\Framework\TestCase;

final class IgnoreListTest extends TestCase
{
    private string $tempFile;

    #[\Override]
    protected function setUp(): void
    {
        $this->tempFile = sys_get_temp_dir() . '/cleanupignore_test_' . bin2hex(random_bytes(6));
        $content = <<<'IGNORE'
# Comment line

commands.txt
*.xml
symfony/console/README.md
vendor/guides/**
IGNORE;
        file_put_contents($this->tempFile, $content);
    }

    #[\Override]
    protected function tearDown(): void
    {
        if (is_file($this->tempFile)) {
            @unlink($this->tempFile);
        }
    }

    public function testMatchesBasenameAndWildcards(): void
    {
        $ignoreList = IgnoreList::fromFiles($this->tempFile);

        self::assertTrue($ignoreList->isIgnored('sprint.migration/commands.txt', 'commands.txt'));
        self::assertTrue($ignoreList->isIgnored('pkg/phpunit.xml', 'phpunit.xml'));
        self::assertFalse($ignoreList->isIgnored('pkg/README.md', 'README.md'));
    }

    public function testMatchesRelativePathsAndDoubleStar(): void
    {
        $ignoreList = IgnoreList::fromFiles($this->tempFile);

        self::assertTrue($ignoreList->isIgnored('symfony/console/README.md', 'README.md'));
        self::assertFalse($ignoreList->isIgnored('symfony/http-client/README.md', 'README.md'));

        self::assertTrue($ignoreList->isIgnored('vendor/guides/intro/setup.md', 'setup.md'));
    }

    public function testHandlesNonExistentFilesGracefully(): void
    {
        $ignoreList = IgnoreList::fromFiles('/non/existent/path/.cleanupignore');

        self::assertFalse($ignoreList->isIgnored('pkg/README.md', 'README.md'));
    }
}
