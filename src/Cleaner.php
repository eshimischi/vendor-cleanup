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

        return $this->scanDirectory($config->targetPath, $regexes, $config->dryRun, $onMatch);
    }

    /**
     * @param list<string> $regexes
     * @param Closure(string): void $onMatch
     */
    private function scanDirectory(string $directory, array $regexes, bool $dryRun, Closure $onMatch): int
    {
        $count = 0;
        $iterator = new FilesystemIterator(
            $directory,
            FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_FILEINFO,
        );

        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
            $pathname = $item->getPathname();

            if ($this->matches($item->getFilename(), $regexes)) {
                $onMatch($pathname);

                if (!$dryRun) {
                    $this->remove($pathname);
                }

                $count++;

                // В режиме dry-run продолжаем сканирование внутри совпавшей папки,
                // а при реальном удалении папки уже не существует — пропускаем спуск.
                if (!$dryRun) {
                    continue;
                }
            }

            if (!$item->isLink() && $item->isDir()) {
                $count += $this->scanDirectory($pathname, $regexes, $dryRun, $onMatch);
            }
        }

        return $count;
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
