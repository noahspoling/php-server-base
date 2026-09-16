<?php

declare(strict_types=1);

namespace App\Tests\Container;

use App\Container\Container;
use App\Container\NotFoundException;
use PHPUnit\Framework\TestCase;

final class ContainerTest extends TestCase
{
    public function test_it_returns_the_value_built_by_a_registered_factory(): void
    {
        $container = new Container([
            Greeter::class => static fn (): Greeter => new Greeter('hello'),
        ]);

        self::assertSame('hello', $container->get(Greeter::class)->greeting);
    }

    public function test_it_memoizes_so_a_binding_resolves_once(): void
    {
        $container = new Container([
            Greeter::class => static fn (): Greeter => new Greeter(bin2hex(random_bytes(4))),
        ]);

        self::assertSame($container->get(Greeter::class), $container->get(Greeter::class));
    }

    public function test_it_passes_itself_to_the_factory_so_bindings_can_depend_on_bindings(): void
    {
        $container = new Container([
            Greeter::class => static fn (): Greeter => new Greeter('hi'),
            Loud::class => static fn (Container $c): Loud => new Loud($c->get(Greeter::class)),
        ]);

        self::assertSame('HI', $container->get(Loud::class)->shout());
    }

    public function test_it_throws_for_an_id_that_is_neither_bound_nor_a_class(): void
    {
        $container = new Container([]);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('database.dsn');

        $container->get('database.dsn');
    }

    public function test_has_reports_bound_ids(): void
    {
        $container = new Container([Greeter::class => static fn (): Greeter => new Greeter('hi')]);

        self::assertTrue($container->has(Greeter::class));
        self::assertFalse($container->has('database.dsn'));
    }

    public function test_it_autowires_a_class_that_has_no_constructor(): void
    {
        self::assertInstanceOf(Clock::class, (new Container([]))->get(Clock::class));
    }

    public function test_it_autowires_typed_class_dependencies_recursively(): void
    {
        $container = new Container([]);

        $report = $container->get(Report::class);

        self::assertInstanceOf(Report::class, $report);
        self::assertSame($container->get(Clock::class), $report->clock);
    }

    public function test_an_autowired_instance_is_memoized_like_a_bound_one(): void
    {
        $container = new Container([]);

        self::assertSame($container->get(Clock::class), $container->get(Clock::class));
    }

    public function test_an_explicit_binding_wins_over_autowiring(): void
    {
        $container = new Container([
            Clock::class => static fn (): Clock => new FrozenClock(),
        ]);

        self::assertInstanceOf(FrozenClock::class, $container->get(Clock::class));
    }

    public function test_it_refuses_to_autowire_a_scalar_constructor_parameter(): void
    {
        $container = new Container([]);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('$dsn');

        $container->get(NeedsScalar::class);
    }

    public function test_has_reports_autowirable_classes(): void
    {
        self::assertTrue((new Container([]))->has(Clock::class));
    }

    public function test_it_resolves_itself_so_a_service_can_depend_on_the_container(): void
    {
        $container = new Container([]);

        self::assertSame($container, $container->get(Container::class));
    }

    public function test_a_service_that_type_hints_the_container_is_autowired_with_this_container(): void
    {
        $container = new Container([Greeter::class => static fn (): Greeter => new Greeter('hi')]);

        $locator = $container->get(Locator::class);

        self::assertSame('hi', $locator->lookUp()->greeting);
    }

    public function test_a_circular_dependency_is_reported_instead_of_recursing_forever(): void
    {
        $container = new Container([]);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Circular dependency');

        $container->get(Chicken::class);
    }
}

final class Chicken
{
    public function __construct(public readonly Egg $egg)
    {
    }
}

final class Egg
{
    public function __construct(public readonly Chicken $chicken)
    {
    }
}

class Clock
{
}

final class FrozenClock extends Clock
{
}

final class Report
{
    public function __construct(public readonly Clock $clock)
    {
    }
}

final class NeedsScalar
{
    public function __construct(public readonly string $dsn)
    {
    }
}

final class Locator
{
    public function __construct(private readonly Container $container)
    {
    }

    public function lookUp(): Greeter
    {
        return $this->container->get(Greeter::class);
    }
}

final class Greeter
{
    public function __construct(public readonly string $greeting)
    {
    }
}

final class Loud
{
    public function __construct(private readonly Greeter $greeter)
    {
    }

    public function shout(): string
    {
        return strtoupper($this->greeter->greeting);
    }
}
