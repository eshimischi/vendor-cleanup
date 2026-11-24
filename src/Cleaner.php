<?php

declare(strict_types=1);

namespace Eshimischi\VendorCleanup;

use Closure;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final readonly class Cleaner
{
	/**
	 * @param Closure(string): void $onMatch
	 */
	public function run(CleanupConfig $config, Closure $onMatch): int
	{
		$count = 0;
		$regexes = array_map($this->patternToRegex(...), $config->patterns);
		
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator(
				$config->targetPath,
				FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_FILEINFO,
			),
			RecursiveIteratorIterator::SELF_FIRST,
		);
		
		/** @var SplFileInfo $item */
		foreach ($iterator as $item) {
			$pathname = $item->getPathname();
			
			if (!$config->dryRun && !file_exists($pathname) && !is_link($pathname)) {
				continue;
			}
			
			if (!$this->matches($item->getFilename(), $regexes)) {
				continue;
			}
			
			$onMatch($pathname);
			
			if (!$config->dryRun) {
				$this->remove($pathname);
			}
			
			$count++;
		}
		
		return $count;
	}
	
	/**
	 * @param list<string> $regexes
	 */
	private function matches(string $filename, array $regexes): bool
	{
		return array_any($regexes, static fn ($regex) => preg_match($regex, $filename) === 1);
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
		
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
			RecursiveIteratorIterator::CHILD_FIRST,
		);
		
		/** @var SplFileInfo $child */
		foreach ($iterator as $child) {
			$childPath = $child->getPathname();
			if ($child->isLink() || !$child->isDir()) {
				@unlink($childPath);
			} else {
				@rmdir($childPath);
			}
		}
		
		@rmdir($path);
	}
}
