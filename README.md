# Composer Root Dependency Guard

A Composer plugin that detects redundant root dependencies and warns users when a package in `composer.json` is already required by another installed package.

This helps maintain a cleaner dependency tree, prevents unnecessary direct dependencies, and reduces the risk of dependency conflicts, dependency deadlocks, and circular dependency issues.

## Why?

When adding dependencies, developers often run:

```bash
composer require vendor/package
```

without realizing that the package is already installed as a transitive dependency of another package.

Example:

```
my-project
└── laravel/framework
    └── symfony/http-foundation
```

Later, someone runs:

```bash
composer require symfony/http-foundation
```

This adds `symfony/http-foundation` as a root dependency in `composer.json`, even though it is already available through `laravel/framework`.

Over time, this can result in:

* Duplicate dependency entries
* Unnecessary root requirements
* Harder dependency management
* Version constraint conflicts
* Dependency resolution issues

## Features

* Detects redundant root dependencies.
* Checks the dependency graph from `composer.lock`.
* Identifies which installed package already requires the dependency.
* Provides clear warnings during Composer operations.
* Helps keep `composer.json` clean.
* Does not modify dependencies automatically.

## How It Works

The plugin listens to Composer's autoload generation event:

```
POST_AUTOLOAD_DUMP
```

After Composer completes dependency installation/update operations, the plugin:

1. Reads `composer.lock`.
2. Builds a dependency map.
3. Checks root dependencies from `composer.json`.
4. Detects packages that are already required by another installed package.
5. Displays a warning.

Example:

```
⚠ Redundant root dependency detected

Package: symfony/http-foundation

Already required by:
  - laravel/framework

Consider removing it from composer.json unless your application directly depends on it.
```

## Installation

Install the plugin in your project:

```bash
composer require --dev chandu7929/composer-root-dependency-guard
```

The plugin will automatically activate after installation.

## Usage

No additional configuration is required.

Continue using Composer normally:

```bash
composer require vendor/package
```

or:

```bash
composer update
```

If a redundant root dependency is detected, the plugin will display a warning.

## Example

Before:

```json
{
    "require": {
        "laravel/framework": "^11.0",
        "symfony/http-foundation": "^7.0"
    }
}
```

Dependency tree:

```
my-project
├── laravel/framework
│   └── symfony/http-foundation
└── symfony/http-foundation
```

Warning:

```
⚠ Redundant root dependency detected

Package: symfony/http-foundation

Already required by:
  - laravel/framework

Consider removing it from composer.json unless your application directly depends on it.
```

## Why Keep a Direct Dependency?

This plugin only warns. There are valid cases where a package should remain a root dependency.

For example:

* Your application directly uses the package APIs.
* You need to control the package version explicitly.
* You want to prevent indirect dependency upgrades.
* The package is part of your public API.

Use your judgement before removing dependencies.

## Limitations

### Warning only

Currently, the plugin does not prevent:

```bash
composer require vendor/package
```

from modifying `composer.json`.

Composer plugins do not currently provide a supported hook before the built-in `require` command updates `composer.json`.

The plugin intentionally works as a dependency hygiene checker.

Future versions may introduce a custom command such as:

```bash
composer guarded-require vendor/package
```

which can validate dependencies before modifying `composer.json`.

## Roadmap

* [ ] Add `composer guarded-require` command.
* [ ] Check dependency chains and display full paths.
* [ ] Add CI-friendly JSON output.
* [ ] Add configuration options.
* [ ] Add allowlist support for intentional root dependencies.
* [ ] Add dependency cleanup suggestions.

## Contributing

Contributions, bug reports, and feature requests are welcome.

Please open an issue or submit a pull request.

## License

MIT License
