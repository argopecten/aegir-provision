<?php

declare(strict_types=1);

namespace Aegir\Provision\Service;

/**
 * Registry for managing pluggable service implementations.
 *
 * Allows third-party extensions to register alternative implementations
 * of HTTP, database, and SSL services.
 */
final class ServiceRegistry
{
    /**
     * @var array<string,array<string,object>>
     */
    private array $services = [];

    /**
     * @var array<string,array<string,callable>>
     */
    private array $factories = [];

    /**
     * @var array<string,string>
     */
    private array $defaultServices = [
        'http' => 'apache',
        'db' => 'mysql',
        'ssl' => 'default',
    ];

    /**
     * Register a service implementation.
     *
     * @param string $type Service type ('http', 'db', 'ssl')
     * @param string $name Service name ('apache', 'nginx', 'mysql', 'postgresql', etc.)
     * @param object $service Service instance implementing the appropriate interface
     */
    public function register(string $type, string $name, object $service): void
    {
        if (!isset($this->services[$type])) {
            $this->services[$type] = [];
        }

        // Validate service implements correct interface
        $this->validateService($type, $service);

        $this->services[$type][$name] = $service;
    }

    /**
     * Register a service factory.
     *
     * Factory is a callable that returns a service instance. The service
     * will be instantiated lazily when first requested.
     *
     * @param string $type Service type ('http', 'db', 'ssl')
     * @param string $name Service name
     * @param callable $factory Factory callable that returns service instance
     */
    public function registerFactory(string $type, string $name, callable $factory): void
    {
        if (!isset($this->factories[$type])) {
            $this->factories[$type] = [];
        }

        $this->factories[$type][$name] = $factory;
    }

    /**
     * Get a service by type and name.
     *
     * @param string $type Service type ('http', 'db', 'ssl')
     * @param string|null $name Service name (null = use default)
     * @return object Service instance
     * @throws \RuntimeException If service not found
     */
    public function get(string $type, ?string $name = null): object
    {
        $name = $name ?? $this->defaultServices[$type] ?? null;
        
        if ($name === null) {
            throw new \RuntimeException("No default service registered for type: {$type}");
        }

        // Check if service already instantiated
        if (isset($this->services[$type][$name])) {
            return $this->services[$type][$name];
        }

        // Try to instantiate from factory
        if (isset($this->factories[$type][$name])) {
            $service = ($this->factories[$type][$name])();
            $this->validateService($type, $service);
            
            // Cache the instantiated service
            $this->services[$type][$name] = $service;
            
            return $service;
        }

        throw new \RuntimeException("Service not found: {$type}/{$name}");
    }

    /**
     * Check if a service is registered.
     */
    public function has(string $type, string $name): bool
    {
        return isset($this->services[$type][$name]) || isset($this->factories[$type][$name]);
    }

    /**
     * Get all registered services of a specific type.
     *
     * @return array<string,object>
     */
    public function getAll(string $type): array
    {
        return $this->services[$type] ?? [];
    }

    /**
     * Set the default service for a type.
     */
    public function setDefault(string $type, string $name): void
    {
        if (!isset($this->services[$type][$name])) {
            throw new \RuntimeException("Cannot set default: service not found: {$type}/{$name}");
        }

        $this->defaultServices[$type] = $name;
    }

    /**
     * Get the default service name for a type.
     */
    public function getDefault(string $type): ?string
    {
        return $this->defaultServices[$type] ?? null;
    }

    /**
     * Validate that a service implements the correct interface.
     *
     * @throws \InvalidArgumentException If service doesn't implement required interface
     */
    private function validateService(string $type, object $service): void
    {
        $requiredInterface = match ($type) {
            'http' => HttpServiceInterface::class,
            'db' => DbServiceInterface::class,
            'ssl' => SslServiceInterface::class,
            default => throw new \InvalidArgumentException("Unknown service type: {$type}"),
        };

        if (!$service instanceof $requiredInterface) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Service for type "%s" must implement %s, got %s',
                    $type,
                    $requiredInterface,
                    get_class($service)
                )
            );
        }
    }
}
