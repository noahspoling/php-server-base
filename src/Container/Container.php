<?php

declare(strict_types=1);

namespace App\Container;

use ReflectionClass;
use ReflectionNamedType;

final class Container
{
    /** @var array<string, mixed> */
    private array $instances = [];

    /** Ids currently being autowired, in order, used to detect cycles. @var array<string, true> */
    private array $resolving = [];

    /**
     * @param array<string, callable(self): mixed> $factories
     */
    public function __construct(private readonly array $factories = [])
    {
    }

    public function get(string $id): mixed
    {
        // The container resolves to itself, so a service that needs to look
        // things up by name (RouteDispatcher resolving controllers) can just
        // type-hint it. Without this, autowiring tries to build a second
        // Container and fails on its array constructor.
        if ($id === self::class) {
            return $this;
        }

        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        if (isset($this->factories[$id])) {
            return $this->instances[$id] = ($this->factories[$id])($this);
        }

        return $this->instances[$id] = $this->autowire($id);
    }

    public function has(string $id): bool
    {
        return isset($this->factories[$id]) || class_exists($id);
    }

    /**
     * Builds an unbound class by reflecting its constructor. Only typed class
     * parameters are resolvable; anything else needs an explicit factory.
     */
    private function autowire(string $id): object
    {
        if (isset($this->resolving[$id])) {
            $chain = implode(' -> ', [...array_keys($this->resolving), $id]);

            throw new NotFoundException("Circular dependency detected: {$chain}");
        }

        if (!class_exists($id)) {
            throw new NotFoundException("Nothing registered for id: {$id}");
        }

        $class = new ReflectionClass($id);

        if (!$class->isInstantiable()) {
            throw new NotFoundException("Cannot autowire {$id}: it is not instantiable. Register a factory for it.");
        }

        $constructor = $class->getConstructor();

        if ($constructor === null) {
            return new $id();
        }

        $this->resolving[$id] = true;

        try {
            $arguments = [];

            foreach ($constructor->getParameters() as $parameter) {
                $type = $parameter->getType();

                if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
                    throw new NotFoundException(sprintf(
                        'Cannot autowire %s: parameter $%s is not a class type. Register a factory for %s.',
                        $id,
                        $parameter->getName(),
                        $id,
                    ));
                }

                $arguments[] = $this->get($type->getName());
            }
        } finally {
            unset($this->resolving[$id]);
        }

        return new $id(...$arguments);
    }
}
