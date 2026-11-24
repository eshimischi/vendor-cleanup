# Vendor Cleanup

[![PHP Version](https://img.shields.io/badge/php-%3E%3D%208.5-8892BF.svg?style=flat-square)](https://php.net/)
[![Coding Standard](https://img.shields.io/badge/PER-3.1-blue)](https://php-fig.org)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

Global CLI utility and Composer plugin to strip tests, documentation, CI configs, and other non-production files from your `vendor` directory.

## Installation

### Global installation (recommended)

```bash
composer global config --no-plugins allow-plugins.eshimischi/vendor-cleanup true
composer global require eshimischi/vendor-cleanup
```

Make sure Composer's global `bin` directory (`composer global config bin-dir --absolute`) is in your system `$PATH`.

### Project installation

```bash
composer config --no-plugins allow-plugins.eshimischi/vendor-cleanup true
composer require eshimischi/vendor-cleanup
```

## Usage

### 1. Automatic mode (Composer Plugin)

When installed globally or locally, the plugin automatically cleans `vendor` after `composer install --no-dev` or `composer update --no-dev`. In dev mode (`--dev`), automatic cleanup is skipped so your local test suites stay intact.

You can customize patterns or disable auto-run in your project's `composer.json`:

```json
{
    "extra": {
        "vendor-cleanup": {
            "auto": true,
            "include": ["*.zip", "*.rar"],
            "exclude": ["doc", "docs"]
        }
    }
}
```

### 2. Manual CLI mode

Run `cleanup` inside any Composer project directory:

```bash
# Preview what will be deleted (dry run)
cleanup --dry

# Clean vendor with verbose output
cleanup -v

# Clean only a specific vendor subdirectory with custom patterns
cleanup -v --path symfony --include "*.zip,*.rar" --exclude "doc,docs,test"
```

### Options

| Flag | Description |
| :--- | :--- |
| `-h`, `--help` | Display help message |
| `-i`, `--include` | Add comma-separated glob patterns |
| `-e`, `--exclude` | Remove comma-separated patterns from defaults |
| `-p`, `--path` | Target a specific subdirectory inside `vendor` |
| `-d`, `--dry` | Run without deleting any files |
| `-v`, `--verbose` | Print every matched path |
