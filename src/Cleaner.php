<?php

declare(strict_types=1);

namespace Eshimischi\VendorCleanup;

use Closure;
use FilesystemIterator;
use SplFileInfo;

final readonly class Cleaner
{
    /**
     * @param Closure(string): void $onMatch
     */
    public function run(CleanupConfig $config, Closure $onMatch): int
    {
        $regexes = array_map($this->patternToRegex(...), $config->patterns);

        return $this->scanDirectory($config->targetPath, $regexes, $config->dryRun, $onMatch, false);
    }

    /**
     * @param list<string> $regexes
     * @param Closure(string): void $onMatch
     */
    private function scanDirectory(
        string $directory,
        array $regexes,
        bool $dryRun,
        Closure $onMatch,
        bool $insideSourceDir,
    ): int {
        $count = 0;
        $iterator = new FilesystemIterator(
            $directory,
            FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_FILEINFO,
        );

        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
            if ($this->isProtectedPhpFile($item)) {
                continue;
            }

            $pathname = $item->getPathname();
            $filename = $item->getFilename();
            $isDirectory = !$item->isLink() && $item->isDir();

            // Не удаляем директории с PSR-4 неймспейсами внутри src/ или lib/ (например, src/Chat/History или src/Test)
            $canMatch = !$isDirectory || !$insideSourceDir;

            if ($canMatch && $this->matches($filename, $regexes)) {
                $onMatch($pathname);

                if (!$dryRun) {
                    $this->remove($pathname);
                }

                $count++;

                if (!$dryRun) {
                    continue;
                }
            }

            if ($isDirectory) {
                $nextInsideSource = $insideSourceDir || in_array(strtolower($filename), ['src', 'lib'], true);
                $count += $this->scanDirectory($pathname, $regexes, $dryRun, $onMatch, $nextInsideSource);
            }
        }

        return $count;
    }

    private function isProtectedPhpFile(SplFileInfo $item): bool
    {
        if ($item->isDir() || $item->isLink()) {
            return false;
        }

        $filename = strtolower($item->getFilename());

        if (str_starts_with($filename, '.php')) {
            return false;
        }

        return str_ends_with($filename, '.php');
    }

    /**
     * @param list<string> $regexes
     */
    private function matches(string $filename, array $regexes): bool
    {
        return array_any($regexes, static fn($regex) => preg_match($regex, $filename) === 1);
    }

    private function patternToRegex(string $pattern): string
    {
        if (str_starts_with($pattern, '{,.}')) {
            $rest = preg_quote(substr($pattern, 4), '/');
            $rest = str_replace(['\*', '\?'], ['.*', '.'], $rest);

            return '/^\.?' . $rest . '$/i';
        }

        $quoted = preg_quote($pattern, '/');
        $quoted = str_replace(['\*', '\?'], ['.*', '.'], $quoted);

        return '/^' . $quoted . '$/i';
    }

    private function remove(string $path): void
    {
        if (is_link($path) || !is_dir($path)) {
            @unlink($path);

            return;
        }

        $iterator = new FilesystemIterator(
            $path,
            FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_FILEINFO,
        );

        /** @var SplFileInfo $child */
        foreach ($iterator as $child) {
            $this->remove($child->getPathname());
        }

        @rmdir($path);
    }
}
