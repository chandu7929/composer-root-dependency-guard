<?php

declare(strict_types=1);

namespace ComposerRootDependencyGuard;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Locker\LockerInterface;
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
        $locker = $this->composer->getLocker();

        if (! $locker->isLocked()) {
            return;
        }

        $lockData = $locker->getLockData();

        $lockedPackages = array_merge(
            $lockData['packages'] ?? [],
            $lockData['packages-dev'] ?? []
        );

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
     * @param array<int, array<string, mixed>> $packages
     *
     * @return array<string, array<string>>
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

    private function isPlatformPackage(string $package): bool
    {
        return $package === 'php'
            || str_starts_with($package, 'ext-')
            || str_starts_with($package, 'lib-')
            || str_starts_with($package, 'composer');
    }
}