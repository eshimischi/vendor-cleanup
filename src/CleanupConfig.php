<?php

declare(strict_types=1);

namespace Eshimischi\VendorCleanup;

final readonly class CleanupConfig
{
    /**
     * Директории, которые безопасно удалять в корне пакетов (вне src/ и lib/)
     *
     * @var list<string>
     */
    public const array DEFAULT_DIRECTORY_PATTERNS = [
        '.git',
        '.github',
        'test',
        'tests',
        'travis',
        'demo',
        'example',
        'examples',
        'doc',
        'docs',
    ];

    /**
     * Файловые паттерны (применяются только к файлам, никогда к директориям)
     *
     * @var list<string>
     */
    public const array DEFAULT_PATTERNS = [
        ...self::DEFAULT_DIRECTORY_PATTERNS,
        'license',
        'license.*',
        'changelog',
        'changelog.*',
        'changes',
        'changes.*',
        'faq',
        'faq.*',
        'contributing',
        'contributing.*',
        'history.*',
        'upgrading',
        'upgrading.*',
        'upgrade',
        'upgrade.*',
        'readme',
        'readme.*',
        '{,.}*.yml',
        '*.md',
        '*.rst',
        '*.xml',
        '*.txt',
        '*.dist',
        '*.neon',
        '.php_cs*',
        '.scrutinizer',
        '.gitignore',
        '.gitattributes',
        '.editorconfig',
        '.phpstorm.meta.php',
        'dockerfile',
        'composer.lock',
    ];

    /**
     * @param list<string> $patterns
     */
    public function __construct(
        public string $targetPath,
        public array $patterns = self::DEFAULT_PATTERNS,
        public bool $dryRun = false,
        public bool $verbose = false,
    ) {}

    /**
     * @param list<string> $include
     * @param list<string> $exclude
     */
    public static function create(
        string $targetPath,
        array $include = [],
        array $exclude = [],
        bool $dryRun = false,
        bool $verbose = false,
    ): self {
        $patterns = self::DEFAULT_PATTERNS
                    |> (static fn(array $items): array => array_merge($items, $include))
                    |> (static fn(array $items): array => array_diff($items, $exclude))
                    |> (static fn(array $items): array => $items
                                                          |> array_filter(...)
                                                          |> array_unique(...)
                                                          |> array_values(...));

        return new self(
            targetPath: $targetPath,
            patterns: $patterns,
            dryRun: $dryRun,
            verbose: $verbose,
        );
    }
}
