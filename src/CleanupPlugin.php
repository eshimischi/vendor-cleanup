<?php

declare(strict_types=1);

namespace Eshimischi\VendorCleanup;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;

final class CleanupPlugin implements PluginInterface, EventSubscriberInterface
{
    private Composer $composer;
    private IOInterface $io;

    #[\Override]
    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    #[\Override]
    public function deactivate(Composer $composer, IOInterface $io): void {}

    #[\Override]
    public function uninstall(Composer $composer, IOInterface $io): void {}

    /**
     * @return array<string, string>
     */
    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [
            ScriptEvents::POST_INSTALL_CMD => 'onPostInstallOrUpdate',
            ScriptEvents::POST_UPDATE_CMD => 'onPostInstallOrUpdate',
        ];
    }

    public function onPostInstallOrUpdate(Event $event): void
    {
        $extra = $this->composer->getPackage()->getExtra();
        $pluginExtra = is_array($extra['vendor-cleanup'] ?? null) ? $extra['vendor-cleanup'] : [];

        $autoMode = $pluginExtra['auto'] ?? true;
        if ($autoMode === false) {
            return;
        }

        if ($autoMode !== 'always' && $event->isDevMode()) {
            return;
        }

        $targets = $this->resolveTargets($pluginExtra);
        if ($targets === []) {
            return;
        }

        $include = $this->normalizeStringList($pluginExtra['include'] ?? []);
        $exclude = $this->normalizeStringList($pluginExtra['exclude'] ?? []);
        $verbose = (bool) ($pluginExtra['verbose'] ?? false) || $this->io->isVerbose();
        $cwd = getcwd();
        $rootIgnoreFile = is_string($cwd) ? $cwd . DIRECTORY_SEPARATOR . '.cleanupignore' : '';

        $cleaner = new Cleaner();

        foreach ($targets as $targetDir) {
            $ignoreFiles = [$targetDir . DIRECTORY_SEPARATOR . '.cleanupignore'];
            if ($rootIgnoreFile !== '') {
                array_unshift($ignoreFiles, $rootIgnoreFile);
            }

            $ignoreList = IgnoreList::fromFiles(...$ignoreFiles);

            $config = CleanupConfig::create(
                targetPath: $targetDir,
                include: $include,
                exclude: $exclude,
                verbose: $verbose,
                ignoreList: $ignoreList,
            );

            $this->io->write(sprintf('<info>Vendor Cleanup:</info> Cleaning %s...', $targetDir));

            $result = $cleaner->run(
                $config,
                function (string $path, bool $isDir, int $bytes) use ($verbose, $targetDir): void {
                    if ($verbose) {
                        $type = $isDir ? 'DIR ' : 'FILE';
                        $relPath = $targetDir
                                   |> strlen(...)
                                   |> (static fn($x) => substr($path, $x))
                                   |> (static fn($x) => ltrim($x, '/\\'));
                        $this->io->write(sprintf('  - [%s] %s (%s)', $type, $relPath, $this->formatBytes($bytes)));
                    }
                },
            );

            $this->io->write(sprintf(
                '<info>Vendor Cleanup:</info> %d object(s) removed (%s freed).',
                $result['count'],
                $this->formatBytes($result['bytes']),
            ));
        }
    }

    /**
     * @param array<mixed, mixed> $pluginExtra
     * @return list<string>
     */
    private function resolveTargets(array $pluginExtra): array
    {
        $customPaths = $this->normalizeStringList($pluginExtra['paths'] ?? []);
        if ($customPaths !== []) {
            $resolved = [];
            foreach ($customPaths as $pattern) {
                $matches = glob($pattern, GLOB_ONLYDIR) ?: [];
                foreach ($matches as $dir) {
                    $real = realpath($dir);
                    if ($real !== false) {
                        $resolved[] = $real;
                    }
                }
            }

            return array_values(array_unique($resolved));
        }

        $vendorDir = $this->composer->getConfig()->get('vendor-dir');
        if (is_string($vendorDir) && is_dir($vendorDir)) {
            $real = realpath($vendorDir);

            if ($real !== false) {
                return [$real];
            }

            return [];
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private function normalizeStringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, is_string(...)));
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }

        return round($bytes / 1024 / 1024, 2) . ' MB';
    }
}
