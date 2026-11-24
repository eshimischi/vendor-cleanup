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
    ];

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
            if ($this->isProtectedSourceFile($item)) {
                continue;
            }

            $pathname = $item->getPathname();
            $filename = $item->getFilename();
            $isDirectory = !$item->isLink() && $item->isDir();

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
                $nextInsideSource = $insideSourceDir || in_array(strtolower($filename), ['src', 'lib', 'install', 'components', 'templates'], true);
                $count += $this->scanDirectory($pathname, $regexes, $dryRun, $onMatch, $nextInsideSource);
            }
        }

        return $count;
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
