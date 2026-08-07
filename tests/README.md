# Tests for Composer Root Dependency Guard Plugin

This directory contains comprehensive unit tests for the `RootDependencyGuardPlugin`.

## Test Coverage

The test suite includes 21 tests covering all aspects of the plugin:

### Plugin Lifecycle Tests
- `testActivateStoresComposerAndIo` - Verifies plugin activation stores Composer and IO instances
- `testDeactivateDoesNotThrow` - Verifies deactivation doesn't throw exceptions
- `testUninstallDoesNotThrow` - Verifies uninstall doesn't throw exceptions
- `testGetSubscribedEventsReturnsCorrectEvent` - Verifies plugin subscribes to POST_AUTOLOAD_DUMP event

### Redundant Dependency Detection Tests
- `testCheckRedundantDependenciesDoesNothingWhenNotLocked` - Skips check when composer.lock is not locked
- `testCheckRedundantDependenciesIgnoresPlatformPackages` - Ignores PHP, extensions, libraries, and composer packages
- `testCheckRedundantDependenciesDetectsRedundantDependencies` - Detects a redundant root dependency
- `testCheckRedundantDependenciesIgnoresDirectDependencies` - Only reports redundant dependencies, not direct ones
- `testCheckRedundantDependenciesWithMultipleRequiredBy` - Handles dependencies required by multiple packages
- `testCheckRedundantDependenciesHandlesDuplicatesInRequiredBy` - Deduplicates packages in the "required by" list
- `testCheckRedundantDependenciesWithDevDependencies` - Checks both require and require-dev dependencies
- `testCheckRedundantDependenciesWithEmptyLockData` - Handles empty lock data gracefully

### Platform Package Detection Tests
- `testIsPlatformPackageDetectsPhp` - Detects `php` as a platform package
- `testIsPlatformPackageDetectsExtensions` - Detects `ext-*` packages
- `testIsPlatformPackageDetectsLibraries` - Detects `lib-*` packages
- `testIsPlatformPackageDetectsComposer` - Detects composer-related packages
- `testIsPlatformPackageReturnsFalseForRegularPackages` - Regular packages are not platform packages

### Dependency Map Building Tests
- `testBuildRequiredByMapBuildsCorrectMap` - Correctly builds the dependency map
- `testBuildRequiredByMapIgnoresPackagesWithoutName` - Ignores packages without a name
- `testBuildRequiredByMapIgnoresPackagesWithoutRequire` - Handles packages without requires

### Root Dependencies Extraction Tests
- `testGetRootDependenciesReturnsCorrectDependencies` - Correctly extracts root dependencies from composer.json

## Running Tests

### Run all tests:
```bash
./vendor/bin/phpunit
```

### Run with code coverage:
```bash
./vendor/bin/phpunit --coverage-html coverage
```

### Run a specific test:
```bash
./vendor/bin/phpunit --filter testActivateStoresComposerAndIo
```

### Run tests with verbose output:
```bash
./vendor/bin/phpunit --verbose
```

## Test Structure

- Tests are organized by functionality
- Each test uses mocking to isolate the plugin's behavior
- Reflection is used to test private methods
- Callback assertions verify complex method outputs

## Dependencies

The tests require:
- PHPUnit 11.x
- Composer 2.x
- PHP 8.1+

These are installed as dev dependencies via:
```bash
composer require --dev phpunit/phpunit composer/composer
```

## Contributing

When adding new features to the plugin:
1. Write corresponding unit tests
2. Ensure all tests pass: `./vendor/bin/phpunit`
3. Maintain or improve code coverage
4. Update this README if adding new test categories

