<?php

declare(strict_types=1);

namespace NullOdyssey\CircuitBreaker;

/**
 * Contract for Circuit Breaker Factory implementations.
 *
 * Defines the interface that circuit breaker factories must implement,
 * providing methods for creating, managing, and resetting circuit breakers
 * across multiple services.
 *
 * @author NullOdyssey
 *
 * @since 1.0.0
 */
interface CircuitBreakerFactoryInterface
{
    /**
     * Get or create a circuit breaker for the specified service.
     *
     * @param string $serviceName The name of the service to protect
     *
     * @return CircuitBreakerInterface The circuit breaker instance for the service
     */
    public function circuitFor(string $serviceName): CircuitBreakerInterface;

    /**
     * Reset all circuit breakers to their initial state.
     *
     * Should reset every circuit breaker managed by this factory,
     * transitioning them all to CLOSED state and clearing their counters.
     */
    public function resetAll(): void;

    /**
     * Reset a specific service's circuit breaker to its initial state.
     *
     * @param string $serviceName The name of the service whose circuit breaker should be reset
     */
    public function resetService(string $serviceName): void;

    /**
     * Get all circuit breakers managed by this factory.
     *
     * @return array<string, CircuitBreakerInterface> Array of circuit breakers indexed by service name
     */
    public function circuitBreakers(): array;
}
