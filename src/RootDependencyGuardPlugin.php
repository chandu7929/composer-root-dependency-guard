<?php

declare(strict_types=1);

namespace ComposerRootDependencyGuard;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;

class RootDependencyGuardPlugin implements PluginInterface, EventSubscriberInterface
{
    private Composer $composer;
    private IOInterface $io;

    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ScriptEvents::POST_AUTOLOAD_DUMP => 'checkRedundantDependencies',
        ];
    }

    public function checkRedundantDependencies(Event $event): void
    {
        try {
            $locker = $this->composer->getLocker();

            if (! $locker->isLocked()) {
                return;
            }

            $lockData = $locker->getLockData();

            if (empty($lockData)) {
                return;
            }

            $lockedPackages = array_merge(
                $lockData['packages'] ?? [],
                $lockData['packages-dev'] ?? []
            );
        } catch (\Exception $e) {
            $this->io->writeError("<error>Error while checking redundant dependencies: {$e->getMessage()}</error>");
            return;
        }

        $rootDependencies = $this->getRootDependencies();

        $requiredByMap = $this->buildRequiredByMap($lockedPackages);

        foreach ($rootDependencies as $dependency) {
            if ($this->isPlatformPackage($dependency)) {
                continue;
            }

            if (! isset($requiredByMap[$dependency])) {
                continue;
            }

            $requiredBy = array_values(array_unique(
                array_filter(
                    $requiredByMap[$dependency],
                    static fn(string $package): bool => $package !== $dependency
                )
            ));

            if ($requiredBy === []) {
                continue;
            }

            $this->warn($dependency, $requiredBy);
        }
    }

    /**
     * Retrieves the list of all root dependencies (both require and require-dev).
     *
     * @return array<string>
     */
    private function getRootDependencies(): array
    {
        $package = $this->composer->getPackage();

        return array_keys(array_merge(
            $package->getRequires(),
            $package->getDevRequires()
        ));
    }

    /**
     * Builds a map of which packages require each dependency.
     *
     * Maps each dependency name to an array of packages that require it.
     * This helps identify when a root dependency is already satisfied by another package.
     *
     * @param array<int, array<string, mixed>> $packages List of locked packages with their requirements
     *
     * @return array<string, array<string>> Map of dependency name => [list of packages requiring it]
     */
    private function buildRequiredByMap(array $packages): array
    {
        $map = [];

        foreach ($packages as $package) {
            if (! isset($package['name'])) {
                continue;
            }

            $packageName = $package['name'];

            foreach (array_keys($package['require'] ?? []) as $dependency) {
                $map[$dependency][] = $packageName;
            }
        }

        return $map;
    }

    /**
     * Displays a warning message about a redundant root dependency.
     *
     * @param string $dependency The redundant root dependency package name
     * @param array<string> $requiredBy List of packages that already require this dependency
     */
    private function warn(string $dependency, array $requiredBy): void
    {
        $this->io->writeError([
            '',
            '<warning>⚠ Redundant root dependency detected</warning>',
            '',
            sprintf(
                'Package: <info>%s</info>',
                $dependency
            ),
            '',
            'Already required by:',
            ...array_map(
                static fn(string $package): string => "  - <comment>{$package}</comment>",
                $requiredBy
            ),
            '',
            'Consider removing it from composer.json unless your application directly depends on it.',
            '',
        ]);
    }

    /**
     * Checks if a package is a platform or virtual package that should be ignored.
     *
     * Platform packages include PHP version constraints, extensions (ext-*),
     * system libraries (lib-*), and Composer-related packages.
     *
     * @param string $package The package name to check
     */
    private function isPlatformPackage(string $package): bool
    {
        return $package === 'php'
            || str_starts_with($package, 'ext-')
            || str_starts_with($package, 'lib-')
            || str_starts_with($package, 'composer');
    }
}