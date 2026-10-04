<?php

declare(strict_types=1);

namespace Tests\Unit\Container;

use App\Container\Container;
use App\Container\ContainerException;
use App\Container\ContainerInterface;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

final class ContainerTest extends TestCase
{
    public function testFactoryIsLazyAndTheInstanceIsShared(): void
    {
        $calls = 0;
        $container = new Container([
            stdClass::class => static function () use (&$calls): stdClass {
                $calls++;

                return new stdClass();
            },
        ]);

        self::assertTrue($container->has(stdClass::class));
        self::assertSame(0, $calls);
        $service = $container->get(stdClass::class);
        self::assertSame($service, $container->get(stdClass::class));
        self::assertSame(1, $calls);
    }

    public function testFactoriesCanResolveDependencies(): void
    {
        $container = new Container([
            DateTimeZone::class => static fn (): DateTimeZone => new DateTimeZone('UTC'),
            stdClass::class => static function (ContainerInterface $container): stdClass {
                $service = new stdClass();
                $service->timezone = $container->get(DateTimeZone::class);

                return $service;
            },
        ]);

        self::assertSame($container->get(DateTimeZone::class), $container->get(stdClass::class)->timezone);
    }

    public function testInterfaceCanBeBoundToItsImplementation(): void
    {
        $container = new Container([
            DateTimeInterface::class => static fn (): DateTimeImmutable => new DateTimeImmutable(),
        ]);

        $service = $container->get(DateTimeInterface::class);

        self::assertInstanceOf(DateTimeImmutable::class, $service);
        self::assertSame($service, $container->get(DateTimeInterface::class));
    }

    public function testUnknownServiceIsRejectedWithoutAutowiring(): void
    {
        $container = new Container([]);

        self::assertFalse($container->has(stdClass::class));
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessageIsOrContains(stdClass::class);

        $container->get(stdClass::class);
    }

    public function testFactoryMustReturnTheRegisteredType(): void
    {
        $container = new Container([
            stdClass::class => static fn (): DateTimeImmutable => new DateTimeImmutable(),
        ]);

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessageIsOrContains(stdClass::class);

        $container->get(stdClass::class);
    }

    public function testDirectDependencyCycleIsRejected(): void
    {
        $container = new Container([
            stdClass::class => static fn (ContainerInterface $container): stdClass => $container->get(stdClass::class),
        ]);

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessageIs('Circular dependency: stdClass -> stdClass');

        $container->get(stdClass::class);
    }

    public function testIndirectDependencyCycleReportsItsPath(): void
    {
        $container = new Container([
            stdClass::class => static function (ContainerInterface $container): stdClass {
                $container->get(DateTimeImmutable::class);

                return new stdClass();
            },
            DateTimeImmutable::class => static function (ContainerInterface $container): DateTimeImmutable {
                $container->get(stdClass::class);

                return new DateTimeImmutable();
            },
        ]);

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessageIs('Circular dependency: stdClass -> DateTimeImmutable -> stdClass');

        $container->get(stdClass::class);
    }

    public function testFactoryCanBeRetriedAfterFailure(): void
    {
        $calls = 0;
        $container = new Container([
            stdClass::class => static function () use (&$calls): stdClass {
                $calls++;

                if ($calls === 1) {
                    throw new RuntimeException('Factory failed.');
                }

                return new stdClass();
            },
        ]);

        try {
            $container->get(stdClass::class);
            self::fail('The factory exception must propagate.');
        } catch (RuntimeException $exception) {
            self::assertSame('Factory failed.', $exception->getMessage());
        }

        $service = $container->get(stdClass::class);
        self::assertSame($service, $container->get(stdClass::class));
        self::assertSame(2, $calls);
    }
}
