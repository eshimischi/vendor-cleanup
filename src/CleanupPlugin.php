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

    public function deactivate(Composer $composer, IOInterface $io): void {}

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
        $cleaner = new Cleaner();

        foreach ($targets as $targetDir) {
            $config = CleanupConfig::create(
                targetPath: $targetDir,
                include: $include,
                exclude: $exclude,
                verbose: $this->io->isVerbose(),
            );

            $this->io->write(sprintf('<info>Vendor Cleanup:</info> Cleaning %s...', $targetDir));

            $deleted = $cleaner->run(
                $config,
                function (string $path): void {
                    if ($this->io->isVerbose()) {
                        $this->io->write('  - Removed ' . $path);
                    }
                },
            );

            $this->io->write(sprintf('<info>Vendor Cleanup:</info> %d object(s) removed.', $deleted));
        }
    }

    /**
     * @param array<mixed, mixed> $pluginExtra
     *
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
}
