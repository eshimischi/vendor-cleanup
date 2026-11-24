<?php

declare(strict_types=1);

namespace Eshimischi\VendorCleanup;

use RuntimeException;

final readonly class VendorLocator
{
    public function resolve(?string $subPath = null, ?string $customDir = null): string
    {
        if ($customDir !== null && $customDir !== '') {
            return $this->resolveProjectRelativeDir($customDir);
        }

        $vendorDir = $this->detectVendorDir();
        $realVendor = realpath($vendorDir);

        if ($realVendor === false || !is_dir($realVendor)) {
            throw new RuntimeException(
                sprintf('Vendor directory not found at "%s". Run command inside a Composer project.', $vendorDir),
            );
        }

        if ($subPath === null || $subPath === '') {
            return $realVendor;
        }

        $target = realpath($realVendor . DIRECTORY_SEPARATOR . ltrim($subPath, '/\\'));

        if ($target === false || !is_dir($target)) {
            throw new RuntimeException(sprintf('Subdirectory not found: "%s".', $subPath));
        }

        if (!str_starts_with($target, $realVendor)) {
            throw new RuntimeException('Path traversal outside of vendor directory is forbidden.');
        }

        return $target;
    }

    private function resolveProjectRelativeDir(string $dir): string
    {
        $projectRoot = $this->detectProjectRoot();
        $realRoot = realpath($projectRoot);

        if ($realRoot === false) {
            throw new RuntimeException('Unable to resolve project root directory.');
        }

        $candidate = str_starts_with($dir, '/')
            ? $dir
            : $realRoot . DIRECTORY_SEPARATOR . ltrim($dir, '/\\');

        $realTarget = realpath($candidate);

        if ($realTarget === false || !is_dir($realTarget)) {
            throw new RuntimeException(sprintf('Target directory not found: "%s".', $dir));
        }

        if (!str_starts_with($realTarget, $realRoot)) {
            throw new RuntimeException('Path traversal outside of project root is forbidden.');
        }

        return $realTarget;
    }

    private function detectProjectRoot(): string
    {
        $cwd = getcwd();
        if ($cwd === false) {
            throw new RuntimeException('Unable to determine current working directory.');
        }

        $current = $cwd;
        while (true) {
            if (is_file($current . DIRECTORY_SEPARATOR . 'composer.json')) {
                return $current;
            }

            $parent = dirname($current);
            if ($parent === $current) {
                break;
            }
            $current = $parent;
        }

        return $cwd;
    }

    private function detectVendorDir(): string
    {
        $envVendor = getenv('COMPOSER_VENDOR_DIR');
        if (is_string($envVendor) && $envVendor !== '') {
            return $envVendor;
        }

        $projectRoot = $this->detectProjectRoot();
        $composerJson = $projectRoot . DIRECTORY_SEPARATOR . 'composer.json';

        if (is_file($composerJson)) {
            return $this->extractVendorFromComposerJson($composerJson, $projectRoot);
        }

        return $projectRoot . DIRECTORY_SEPARATOR . 'vendor';
    }

    private function extractVendorFromComposerJson(string $composerJsonPath, string $projectRoot): string
    {
        $content = file_get_contents($composerJsonPath);
        if ($content === false) {
            return $projectRoot . DIRECTORY_SEPARATOR . 'vendor';
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            return $projectRoot . DIRECTORY_SEPARATOR . 'vendor';
        }

        $config = $data['config'] ?? null;
        if (!is_array($config)) {
            return $projectRoot . DIRECTORY_SEPARATOR . 'vendor';
        }

        $configured = $config['vendor-dir'] ?? null;
        if (!is_string($configured) || $configured === '') {
            return $projectRoot . DIRECTORY_SEPARATOR . 'vendor';
        }

        if (str_starts_with($configured, '/')) {
            return $configured;
        }

        return $projectRoot . DIRECTORY_SEPARATOR . $configured;
    }
}
