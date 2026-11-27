<?php

declare(strict_types=1);

namespace Eshimischi\VendorCleanup;

final readonly class IgnoreList
{
    /**
     * @param list<string> $regexes
     */
    public function __construct(
        private array $regexes = [],
    ) {}

    public static function fromFiles(string ...$filePaths): self
    {
        $regexes = [];

        foreach ($filePaths as $filePath) {
            if (!is_file($filePath) || !is_readable($filePath)) {
                continue;
            }

            $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines === false) {
                continue;
            }

            foreach ($lines as $line) {
                $trimmed = trim($line);
                if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                    continue;
                }

                foreach (self::ruleToRegexes($trimmed) as $regex) {
                    $regexes[] = $regex;
                }
            }
        }

        return new self(array_values(array_unique($regexes)));
    }

    public function isIgnored(string $relativePath, string $basename): bool
    {
        if ($this->regexes === []) {
            return false;
        }

        $normalizedRel = str_replace('\\', '/', ltrim($relativePath, '/\\'));

        return array_any(
            $this->regexes,
            static fn($regex) => preg_match($regex, $basename) === 1 || preg_match($regex, $normalizedRel) === 1,
        );
    }

    /**
     * @return list<string>
     */
    private static function ruleToRegexes(string $rule): array
    {
        $anchored = str_starts_with($rule, '/');
        $normalized = str_replace('\\', '/', trim($rule, " \t\n\r\0\x0B/"));

        $patterns = [$normalized];

        // Если правило заканчивается на /** или /* (например, docs/**), защищаем и саму директорию docs
        if (str_ends_with($normalized, '/**')) {
            $patterns[] = substr($normalized, 0, -3);
        } elseif (str_ends_with($normalized, '/*')) {
            $patterns[] = substr($normalized, 0, -2);
        }

        $regexes = [];
        foreach ($patterns as $pattern) {
            $quoted = preg_quote($pattern, '/');
            $quoted = str_replace(['\*\*', '\*', '\?'], ['.*', '[^\/]*', '[^\/]'], $quoted);

            $prefix = $anchored ? '^' : '(?:^|.*\/)';
            $regexes[] = '/' . $prefix . $quoted . '$/i';
        }

        return $regexes;
    }
}
