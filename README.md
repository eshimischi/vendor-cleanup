# Vendor Cleanup

[![PHP Version](https://shields.io)](https://php.net)
[![Coding Standard](https://shields.io)](https://php-fig.org)
[![License](https://shields.io)](LICENSE)

Global CLI utility and Composer plugin to strip tests, documentation, CI configs, and other non-production files from `vendor` or custom project directories while protecting source code (`.php`, `.ts`, `.js`, `.vue`, `.css`, etc.) and symlinks.

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
composer require --dev eshimischi/vendor-cleanup
```

## Usage

### 1. Automatic mode (Composer Plugin)

By default, the plugin automatically cleans `vendor` after `composer install --no-dev` or `composer update --no-dev`.

You can customize target paths, verbosity, or trigger mode in your project's `composer.json`:

```json
{
    "extra": {
        "vendor-cleanup": {
            "auto": "always",
            "verbose": true,
            "paths": [
                "modules/dalee.*",
                "modules/sprint.migration"
            ],
            "include": ["*.zip", "*.rar"],
            "exclude": ["doc", "docs"]
        }
    }
}
```

* `auto`: `true` (default — runs only on `--no-dev`), `"always"` (runs on every `install`/`update`), or `false` (disabled).
* `verbose`: `true` prints every removed `[DIR ]` / `[FILE]` and freed disk space.
* `paths`: array of project-relative directories or glob patterns (defaults to `vendor`).

### 2. `.cleanupignore`

Place a `.cleanupignore` file in your project root (or inside the target directory) to protect specific files, extensions, or paths from removal:

```gitignore
# Keep migration CLI cheatsheets
commands.txt
commands-en.txt

# Keep documentation for a specific package
symfony/console/README.md
maennchen/zipstream-php/guides/**
```

### 3. Manual CLI mode

Run `cleanup` inside any Composer project directory:

```bash
# Preview what will be deleted (dry run)
cleanup --dry

# Clean vendor with detailed output
cleanup -v

# Clean a specific directory relative to project root
cleanup -v --dir modules/dalee.core

# Clean a specific vendor subdirectory with custom patterns
cleanup -v --path symfony --include "*.zip,*.rar" --exclude "doc,docs,test"
```

### Options

| Flag | Description |
| :--- | :--- |
| `-h`, `--help` | Display help message |
| `-i`, `--include` | Add comma-separated glob patterns |
| `-e`, `--exclude` | Remove comma-separated patterns from defaults |
| `-p`, `--path` | Target a specific subdirectory inside `vendor` |
| `-r`, `--dir` | Target a custom directory relative to project root |
| `--ignore-file` | Path to a custom `.cleanupignore` file |
| `-d`, `--dry` | Run without deleting any files |
| `-v`, `--verbose` | Display detailed list of removed directories, files, and freed size |
