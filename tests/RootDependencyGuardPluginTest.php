<?php

declare(strict_types=1);

namespace ComposerRootDependencyGuard\Tests;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Package\RootPackageInterface;
use Composer\Package\Link;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use ComposerRootDependencyGuard\RootDependencyGuardPlugin;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class RootDependencyGuardPluginTest extends TestCase
{
    private RootDependencyGuardPlugin $plugin;
    private Composer&MockObject $composerMock;
    private IOInterface&MockObject $ioMock;

    protected function setUp(): void
    {
        $this->plugin = new RootDependencyGuardPlugin();
        $this->composerMock = $this->createMock(Composer::class);
        $this->ioMock = $this->createMock(IOInterface::class);
    }

    public function testActivateStoresComposerAndIo(): void
    {
        $this->plugin->activate($this->composerMock, $this->ioMock);

        // Use reflection to access private properties
        $reflectionClass = new \ReflectionClass($this->plugin);

        $composerProperty = $reflectionClass->getProperty('composer');
        $composerProperty->setAccessible(true);

        $ioProperty = $reflectionClass->getProperty('io');
        $ioProperty->setAccessible(true);

        $this->assertSame($this->composerMock, $composerProperty->getValue($this->plugin));
        $this->assertSame($this->ioMock, $ioProperty->getValue($this->plugin));
    }

    public function testDeactivateDoesNotThrow(): void
    {
        $this->plugin->deactivate($this->composerMock, $this->ioMock);
        $this->assertTrue(true);
    }

    public function testUninstallDoesNotThrow(): void
    {
        $this->plugin->uninstall($this->composerMock, $this->ioMock);
        $this->assertTrue(true);
    }

    public function testGetSubscribedEventsReturnsCorrectEvent(): void
    {
        $events = RootDependencyGuardPlugin::getSubscribedEvents();

        $this->assertIsArray($events);
        $this->assertArrayHasKey(ScriptEvents::POST_AUTOLOAD_DUMP, $events);
        $this->assertSame('checkRedundantDependencies', $events[ScriptEvents::POST_AUTOLOAD_DUMP]);
    }

    public function testCheckRedundantDependenciesDoesNothingWhenNotLocked(): void
    {
        $this->plugin->activate($this->composerMock, $this->ioMock);

        $lockerMock = $this->createMock(\Composer\Package\Locker::class);
        $lockerMock->expects($this->once())
            ->method('isLocked')
            ->willReturn(false);

        $this->composerMock->expects($this->once())
            ->method('getLocker')
            ->willReturn($lockerMock);

        $eventMock = $this->createMock(Event::class);

        $this->ioMock->expects($this->never())->method('writeError');

        $this->plugin->checkRedundantDependencies($eventMock);
    }

    public function testCheckRedundantDependenciesIgnoresPlatformPackages(): void
    {
        $this->plugin->activate($this->composerMock, $this->ioMock);

        $packageMock = $this->createMock(RootPackageInterface::class);
        $packageMock->expects($this->once())
            ->method('getRequires')
            ->willReturn(['php' => $this->createMock(Link::class)]);
        $packageMock->expects($this->once())
            ->method('getDevRequires')
            ->willReturn([]);

        $this->composerMock->expects($this->once())
            ->method('getPackage')
            ->willReturn($packageMock);

        $lockerMock = $this->createMock(\Composer\Package\Locker::class);
        $lockerMock->expects($this->once())
            ->method('isLocked')
            ->willReturn(true);
        $lockerMock->expects($this->once())
            ->method('getLockData')
            ->willReturn(['packages' => [], 'packages-dev' => []]);

        $this->composerMock->expects($this->once())
            ->method('getLocker')
            ->willReturn($lockerMock);

        $eventMock = $this->createMock(Event::class);

        $this->ioMock->expects($this->never())->method('writeError');

        $this->plugin->checkRedundantDependencies($eventMock);
    }

    public function testCheckRedundantDependenciesDetectsRedundantDependencies(): void
    {
        $this->plugin->activate($this->composerMock, $this->ioMock);

        $packageMock = $this->createMock(RootPackageInterface::class);
        $packageMock->expects($this->once())
            ->method('getRequires')
            ->willReturn(['symfony/http-foundation' => $this->createMock(Link::class)]);
        $packageMock->expects($this->once())
            ->method('getDevRequires')
            ->willReturn([]);

        $this->composerMock->expects($this->once())
            ->method('getPackage')
            ->willReturn($packageMock);

        $lockerMock = $this->createMock(\Composer\Package\Locker::class);
        $lockerMock->expects($this->once())
            ->method('isLocked')
            ->willReturn(true);
        $lockerMock->expects($this->once())
            ->method('getLockData')
            ->willReturn([
                'packages' => [
                    [
                        'name' => 'laravel/framework',
                        'require' => [
                            'symfony/http-foundation' => '^7.0',
                        ],
                    ],
                    [
                        'name' => 'symfony/http-foundation',
                        'require' => [],
                    ],
                ],
                'packages-dev' => [],
            ]);

        $this->composerMock->expects($this->once())
            ->method('getLocker')
            ->willReturn($lockerMock);

        $eventMock = $this->createMock(Event::class);

        $this->ioMock->expects($this->once())
            ->method('writeError')
            ->with($this->callback(static function ($output): bool {
                return is_array($output)
                    && in_array('<warning>⚠ Redundant root dependency detected</warning>', $output, true)
                    && in_array('Package: <info>symfony/http-foundation</info>', $output, true);
            }));

        $this->plugin->checkRedundantDependencies($eventMock);
    }

    public function testCheckRedundantDependenciesIgnoresDirectDependencies(): void
    {
        $this->plugin->activate($this->composerMock, $this->ioMock);

        $packageMock = $this->createMock(RootPackageInterface::class);
        $packageMock->expects($this->once())
            ->method('getRequires')
            ->willReturn(['symfony/http-foundation' => $this->createMock(Link::class)]);
        $packageMock->expects($this->once())
            ->method('getDevRequires')
            ->willReturn([]);

        $this->composerMock->expects($this->once())
            ->method('getPackage')
            ->willReturn($packageMock);

        $lockerMock = $this->createMock(\Composer\Package\Locker::class);
        $lockerMock->expects($this->once())
            ->method('isLocked')
            ->willReturn(true);
        $lockerMock->expects($this->once())
            ->method('getLockData')
            ->willReturn([
                'packages' => [
                    [
                        'name' => 'symfony/http-foundation',
                        'require' => [],
                    ],
                ],
                'packages-dev' => [],
            ]);

        $this->composerMock->expects($this->once())
            ->method('getLocker')
            ->willReturn($lockerMock);

        $eventMock = $this->createMock(Event::class);

        $this->ioMock->expects($this->never())->method('writeError');

        $this->plugin->checkRedundantDependencies($eventMock);
    }

    public function testCheckRedundantDependenciesWithMultipleRequiredBy(): void
    {
        $this->plugin->activate($this->composerMock, $this->ioMock);

        $packageMock = $this->createMock(RootPackageInterface::class);
        $packageMock->expects($this->once())
            ->method('getRequires')
            ->willReturn(['symfony/http-foundation' => $this->createMock(Link::class)]);
        $packageMock->expects($this->once())
            ->method('getDevRequires')
            ->willReturn([]);

        $this->composerMock->expects($this->once())
            ->method('getPackage')
            ->willReturn($packageMock);

        $lockerMock = $this->createMock(\Composer\Package\Locker::class);
        $lockerMock->expects($this->once())
            ->method('isLocked')
            ->willReturn(true);
        $lockerMock->expects($this->once())
            ->method('getLockData')
            ->willReturn([
                'packages' => [
                    [
                        'name' => 'laravel/framework',
                        'require' => ['symfony/http-foundation' => '^7.0'],
                    ],
                    [
                        'name' => 'symfony/console',
                        'require' => ['symfony/http-foundation' => '^7.0'],
                    ],
                    [
                        'name' => 'symfony/http-foundation',
                        'require' => [],
                    ],
                ],
                'packages-dev' => [],
            ]);

        $this->composerMock->expects($this->once())
            ->method('getLocker')
            ->willReturn($lockerMock);

        $eventMock = $this->createMock(Event::class);

        $this->ioMock->expects($this->once())
            ->method('writeError')
            ->with($this->callback(static function ($output): bool {
                return is_array($output)
                    && in_array('  - <comment>laravel/framework</comment>', $output, true)
                    && in_array('  - <comment>symfony/console</comment>', $output, true);
            }));

        $this->plugin->checkRedundantDependencies($eventMock);
    }

    public function testCheckRedundantDependenciesHandlesDuplicatesInRequiredBy(): void
    {
        $this->plugin->activate($this->composerMock, $this->ioMock);

        $packageMock = $this->createMock(RootPackageInterface::class);
        $packageMock->expects($this->once())
            ->method('getRequires')
            ->willReturn(['symfony/http-foundation' => $this->createMock(Link::class)]);
        $packageMock->expects($this->once())
            ->method('getDevRequires')
            ->willReturn([]);

        $this->composerMock->expects($this->once())
            ->method('getPackage')
            ->willReturn($packageMock);

        $lockerMock = $this->createMock(\Composer\Package\Locker::class);
        $lockerMock->expects($this->once())
            ->method('isLocked')
            ->willReturn(true);
        $lockerMock->expects($this->once())
            ->method('getLockData')
            ->willReturn([
                'packages' => [
                    [
                        'name' => 'laravel/framework',
                        'require' => ['symfony/http-foundation' => '^7.0'],
                    ],
                ],
                'packages-dev' => [],
            ]);

        $this->composerMock->expects($this->once())
            ->method('getLocker')
            ->willReturn($lockerMock);

        $eventMock = $this->createMock(Event::class);

        $this->ioMock->expects($this->once())
            ->method('writeError')
            ->with($this->callback(static function ($output): bool {
                return is_array($output)
                    && count(array_filter($output, static fn($line) => str_contains($line ?? '', 'laravel/framework'))) === 1;
            }));

        $this->plugin->checkRedundantDependencies($eventMock);
    }

    public function testIsPlatformPackageDetectsPhp(): void
    {
        $this->plugin->activate($this->composerMock, $this->ioMock);

        $reflectionClass = new \ReflectionClass($this->plugin);
        $method = $reflectionClass->getMethod('isPlatformPackage');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($this->plugin, 'php'));
    }

    public function testIsPlatformPackageDetectsExtensions(): void
    {
        $this->plugin->activate($this->composerMock, $this->ioMock);

        $reflectionClass = new \ReflectionClass($this->plugin);
        $method = $reflectionClass->getMethod('isPlatformPackage');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($this->plugin, 'ext-curl'));
        $this->assertTrue($method->invoke($this->plugin, 'ext-json'));
    }

    public function testIsPlatformPackageDetectsLibraries(): void
    {
        $this->plugin->activate($this->composerMock, $this->ioMock);

        $reflectionClass = new \ReflectionClass($this->plugin);
        $method = $reflectionClass->getMethod('isPlatformPackage');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($this->plugin, 'lib-openssl'));
        $this->assertTrue($method->invoke($this->plugin, 'lib-zlib'));
    }

    public function testIsPlatformPackageDetectsComposer(): void
    {
        $this->plugin->activate($this->composerMock, $this->ioMock);

        $reflectionClass = new \ReflectionClass($this->plugin);
        $method = $reflectionClass->getMethod('isPlatformPackage');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($this->plugin, 'composer'));
        $this->assertTrue($method->invoke($this->plugin, 'composer-plugin-api'));
    }

    public function testIsPlatformPackageReturnsFalseForRegularPackages(): void
    {
        $this->plugin->activate($this->composerMock, $this->ioMock);

        $reflectionClass = new \ReflectionClass($this->plugin);
        $method = $reflectionClass->getMethod('isPlatformPackage');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($this->plugin, 'symfony/http-foundation'));
        $this->assertFalse($method->invoke($this->plugin, 'laravel/framework'));
    }

    public function testBuildRequiredByMapBuildsCorrectMap(): void
    {
        $this->plugin->activate($this->composerMock, $this->ioMock);

        $reflectionClass = new \ReflectionClass($this->plugin);
        $method = $reflectionClass->getMethod('buildRequiredByMap');
        $method->setAccessible(true);

        $packages = [
            [
                'name' => 'laravel/framework',
                'require' => [
                    'symfony/http-foundation' => '^7.0',
                    'symfony/routing' => '^7.0',
                ],
            ],
            [
                'name' => 'symfony/http-foundation',
                'require' => [],
            ],
        ];

        $map = $method->invoke($this->plugin, $packages);

        $this->assertArrayHasKey('symfony/http-foundation', $map);
        $this->assertArrayHasKey('symfony/routing', $map);
        $this->assertContains('laravel/framework', $map['symfony/http-foundation']);
        $this->assertContains('laravel/framework', $map['symfony/routing']);
    }

    public function testBuildRequiredByMapIgnoresPackagesWithoutName(): void
    {
        $this->plugin->activate($this->composerMock, $this->ioMock);

        $reflectionClass = new \ReflectionClass($this->plugin);
        $method = $reflectionClass->getMethod('buildRequiredByMap');
        $method->setAccessible(true);

        $packages = [
            [
                'require' => ['symfony/http-foundation' => '^7.0'],
            ],
            [
                'name' => 'laravel/framework',
                'require' => ['symfony/routing' => '^7.0'],
            ],
        ];

        $map = $method->invoke($this->plugin, $packages);

        $this->assertArrayHasKey('symfony/routing', $map);
        $this->assertNotContains('', $map['symfony/routing']);
    }

    public function testBuildRequiredByMapIgnoresPackagesWithoutRequire(): void
    {
        $this->plugin->activate($this->composerMock, $this->ioMock);

        $reflectionClass = new \ReflectionClass($this->plugin);
        $method = $reflectionClass->getMethod('buildRequiredByMap');
        $method->setAccessible(true);

        $packages = [
            [
                'name' => 'laravel/framework',
            ],
        ];

        $map = $method->invoke($this->plugin, $packages);

        $this->assertIsArray($map);
        $this->assertEmpty($map);
    }

    public function testGetRootDependenciesReturnsCorrectDependencies(): void
    {
        $requireLink1 = $this->createMock(Link::class);
        $requireLink2 = $this->createMock(Link::class);
        $devRequireLink = $this->createMock(Link::class);

        $packageMock = $this->createMock(RootPackageInterface::class);
        $packageMock->expects($this->once())
            ->method('getRequires')
            ->willReturn([
                'symfony/http-foundation' => $requireLink1,
                'laravel/framework' => $requireLink2,
            ]);
        $packageMock->expects($this->once())
            ->method('getDevRequires')
            ->willReturn([
                'phpunit/phpunit' => $devRequireLink,
            ]);

        $this->composerMock->expects($this->once())
            ->method('getPackage')
            ->willReturn($packageMock);

        $this->plugin->activate($this->composerMock, $this->ioMock);

        $reflectionClass = new \ReflectionClass($this->plugin);
        $method = $reflectionClass->getMethod('getRootDependencies');
        $method->setAccessible(true);

        $dependencies = $method->invoke($this->plugin);

        $this->assertCount(3, $dependencies);
        $this->assertContains('symfony/http-foundation', $dependencies);
        $this->assertContains('laravel/framework', $dependencies);
        $this->assertContains('phpunit/phpunit', $dependencies);
    }

    public function testCheckRedundantDependenciesWithDevDependencies(): void
    {
        $this->plugin->activate($this->composerMock, $this->ioMock);

        $packageMock = $this->createMock(RootPackageInterface::class);
        $packageMock->expects($this->once())
            ->method('getRequires')
            ->willReturn([]);
        $packageMock->expects($this->once())
            ->method('getDevRequires')
            ->willReturn(['phpunit/phpunit' => $this->createMock(Link::class)]);

        $this->composerMock->expects($this->once())
            ->method('getPackage')
            ->willReturn($packageMock);

        $lockerMock = $this->createMock(\Composer\Package\Locker::class);
        $lockerMock->expects($this->once())
            ->method('isLocked')
            ->willReturn(true);
        $lockerMock->expects($this->once())
            ->method('getLockData')
            ->willReturn([
                'packages' => [
                    [
                        'name' => 'symfony/testing',
                        'require' => ['phpunit/phpunit' => '^11.0'],
                    ],
                ],
                'packages-dev' => [],
            ]);

        $this->composerMock->expects($this->once())
            ->method('getLocker')
            ->willReturn($lockerMock);

        $eventMock = $this->createMock(Event::class);

        $this->ioMock->expects($this->once())
            ->method('writeError')
            ->with($this->callback(static function ($output): bool {
                return is_array($output)
                    && in_array('Package: <info>phpunit/phpunit</info>', $output, true);
            }));

        $this->plugin->checkRedundantDependencies($eventMock);
    }

    public function testCheckRedundantDependenciesWithEmptyLockData(): void
    {
        $this->plugin->activate($this->composerMock, $this->ioMock);

        $packageMock = $this->createMock(RootPackageInterface::class);
        $packageMock->expects($this->never())
            ->method('getRequires');
        $packageMock->expects($this->never())
            ->method('getDevRequires');

        $this->composerMock->expects($this->never())
            ->method('getPackage');

        $lockerMock = $this->createMock(\Composer\Package\Locker::class);
        $lockerMock->expects($this->once())
            ->method('isLocked')
            ->willReturn(true);
        $lockerMock->expects($this->once())
            ->method('getLockData')
            ->willReturn([]);

        $this->composerMock->expects($this->once())
            ->method('getLocker')
            ->willReturn($lockerMock);

        $eventMock = $this->createMock(Event::class);

        $this->ioMock->expects($this->never())->method('writeError');

        $this->plugin->checkRedundantDependencies($eventMock);
    }
}

