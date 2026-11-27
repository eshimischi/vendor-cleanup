<?php

declare(strict_types=1);

namespace Eshimischi\VendorCleanup;

use Closure;
use FilesystemIterator;
use SplFileInfo;

final readonly class Cleaner
{
    /**
     * @var list<string>
     */
    private const array PROTECTED_EXTENSIONS = [
        'php',
        'ts',
        'tsx',
        'js',
        'jsx',
        'mjs',
        'cjs',
        'vue',
        'svelte',
        'css',
        'scss',
        'sass',
        'less',
        'rs',
    ];

    /**
     * @param Closure(string, bool, int): void $onMatch
     * @return array{count: int, bytes: int}
     */
    public function run(CleanupConfig $config, Closure $onMatch): array
    {
        $regexes = array_map($this->patternToRegex(...), $config->patterns);

        return $this->scanDirectory(
            rootTarget: $config->targetPath,
            directory: $config->targetPath,
            regexes: $regexes,
            ignoreList: $config->ignoreList,
            dryRun: $config->dryRun,
            onMatch: $onMatch,
            insideSourceDir: false,
        );
    }

    /**
     * @param list<string> $regexes
     * @param Closure(string, bool, int): void $onMatch
     * @return array{count: int, bytes: int}
     */
    private function scanDirectory(
        string $rootTarget,
        string $directory,
        array $regexes,
        IgnoreList $ignoreList,
        bool $dryRun,
        Closure $onMatch,
        bool $insideSourceDir,
    ): array {
        $count = 0;
        $bytes = 0;
        $iterator = new FilesystemIterator(
            $directory,
            FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_FILEINFO,
        );

        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
            if ($this->isProtectedSourceFile($item)) {
                continue;
            }

            $pathname = $item->getPathname();
            $filename = $item->getFilename();
            $relativePath = $rootTarget
                            |> strlen(...)
                            |> (static fn($x) => substr($pathname, $x))
                            |> (static fn($x) => ltrim($x, '/\\'));

            if ($ignoreList->isIgnored($relativePath, $filename)) {
                continue;
            }

            $isDirectory = !$item->isLink() && $item->isDir();
            $canMatch = !$isDirectory || !$insideSourceDir;

            if ($canMatch && $this->matches($filename, $regexes)) {
                $size = $this->calculateSize($pathname);
                $onMatch($pathname, $isDirectory, $size);

                if (!$dryRun) {
                    $this->remove($pathname);
                }

                $count++;
                $bytes += $size;

                if (!$dryRun) {
                    continue;
                }
            }

            if ($isDirectory) {
                $nextInsideSource = $insideSourceDir
                    || in_array(strtolower($filename), ['src', 'lib', 'install', 'components', 'templates'], true);
                $sub = $this->scanDirectory(
                    rootTarget: $rootTarget,
                    directory: $pathname,
                    regexes: $regexes,
                    ignoreList: $ignoreList,
                    dryRun: $dryRun,
                    onMatch: $onMatch,
                    insideSourceDir: $nextInsideSource,
                );
                $count += $sub['count'];
                $bytes += $sub['bytes'];
            }
        }

        return ['count' => $count, 'bytes' => $bytes];
    }

    private function isProtectedSourceFile(SplFileInfo $item): bool
    {
        if ($item->isDir() || $item->isLink()) {
            return false;
        }

        $filename = strtolower($item->getFilename());

        if (str_starts_with($filename, '.')) {
            return false;
        }

        $extension = strtolower($item->getExtension());

        return in_array($extension, self::PROTECTED_EXTENSIONS, true);
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

    private function calculateSize(string $path): int
    {
        if (is_link($path)) {
            return 0;
        }

        if (!is_dir($path)) {
            $size = @filesize($path);

            if ($size !== false) {
                return $size;
            }

            return 0;
        }

        $total = 0;
        $iterator = new FilesystemIterator(
            $path,
            FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_FILEINFO,
        );

        /** @var SplFileInfo $child */
        foreach ($iterator as $child) {
            $total += $this->calculateSize($child->getPathname());
        }

        return $total;
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
