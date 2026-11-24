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
	public static function getSubscribedEvents(): array
	{
		return [
			ScriptEvents::POST_INSTALL_CMD => 'onPostInstallOrUpdate',
			ScriptEvents::POST_UPDATE_CMD => 'onPostInstallOrUpdate',
		];
	}
	
	public function onPostInstallOrUpdate(Event $event): void
	{
		// В dev-режиме автоочистку не запускаем, чтобы не сломать локальную разработку
		if ($event->isDevMode()) {
			return;
		}
		
		$extra = $this->composer->getPackage()->getExtra();
		$pluginExtra = is_array($extra['vendor-cleanup'] ?? null) ? $extra['vendor-cleanup'] : [];
		
		if (($pluginExtra['auto'] ?? true) === false) {
			return;
		}
		
		$vendorDir = $this->composer->getConfig()->get('vendor-dir');
		if (!is_string($vendorDir) || !is_dir($vendorDir)) {
			return;
		}
		
		$include = $this->normalizeStringList($pluginExtra['include'] ?? []);
		$exclude = $this->normalizeStringList($pluginExtra['exclude'] ?? []);
		
		$config = CleanupConfig::create(
			targetPath: $vendorDir,
			include: $include,
			exclude: $exclude,
			verbose: $this->io->isVerbose(),
		);
		
		$this->io->write('<info>Vendor Cleanup:</info> Cleaning production vendor directory...');
		
		$deleted = new Cleaner()->run(
			$config,
			function (string $path): void {
				if ($this->io->isVerbose()) {
					$this->io->write('  - Removed ' . $path);
				}
			},
		);
		
		$this->io->write(sprintf('<info>Vendor Cleanup:</info> %d object(s) removed.', $deleted));
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
