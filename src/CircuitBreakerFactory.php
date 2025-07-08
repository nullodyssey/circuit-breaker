<?php

declare(strict_types=1);

namespace NullOdyssey\CircuitBreaker;

/**
 * Factory for creating and managing Circuit Breaker instances.
 *
 * This factory maintains a registry of circuit breakers per service name,
 * ensuring that each service gets its own circuit breaker instance with
 * independent state tracking. It also provides bulk operations for
 * managing multiple circuit breakers.
 *
 * @author NullOdyssey
 *
 * @since 1.0.0
 */
class CircuitBreakerFactory implements CircuitBreakerFactoryInterface
{
    /**
     * @var array<string, CircuitBreakerInterface> Registry of circuit breakers by service name
     */
    private array $circuitBreakers = [];

    /**
     * Create a new Circuit Breaker Factory.
     *
     * @param int                                    $defaultFailureThreshold       Default number of failures before opening circuit (default: 5)
     * @param int                                    $defaultRecoveryTimeoutSeconds Default time in seconds before attempting reset (default: 60)
     * @param int                                    $defaultHalfOpenMaxCalls       Default maximum calls in half-open state (default: 3)
     * @param CircuitBreakerStoreInterface|null $stateStore                    Optional state store for persistent circuit breakers
     */
    public function __construct(
        private readonly int                           $defaultFailureThreshold = 5,
        private readonly int                           $defaultRecoveryTimeoutSeconds = 60,
        private readonly int                           $defaultHalfOpenMaxCalls = 3,
        private readonly ?CircuitBreakerStoreInterface $stateStore = null,
    ) {
    }

    /**
     * Get or create a circuit breaker for the specified service.
     *
     * If a circuit breaker for the service already exists, it returns the existing instance.
     * Otherwise, creates a new circuit breaker with the factory's default configuration.
     *
     * @param string $serviceName The name of the service to protect
     *
     * @return CircuitBreakerInterface The circuit breaker instance for the service
     */
    public function circuitFor(string $serviceName): CircuitBreakerInterface
    {
        $circuitBreaker = $this->circuitBreakers[$serviceName] ?? null;
        if ($circuitBreaker instanceof CircuitBreakerInterface) {
            return $circuitBreaker;
        }

        $this->circuitBreakers[$serviceName] = $this->createCircuitBreaker($serviceName);

        return $this->circuitBreakers[$serviceName];
    }

    private function createCircuitBreaker(
        string $serviceName
    ): CircuitBreakerInterface {
        if ($this->stateStore instanceof CircuitBreakerStoreInterface) {
            return new PersistentCircuitBreaker(
                serviceName: $serviceName,
                stateStore: $this->stateStore,
                failureThreshold: $this->defaultFailureThreshold,
                recoveryTimeoutSeconds: $this->defaultRecoveryTimeoutSeconds,
                halfOpenMaxCalls: $this->defaultHalfOpenMaxCalls,
            );
        }

        return new CircuitBreaker(
            serviceName: $serviceName,
            failureThreshold: $this->defaultFailureThreshold,
            recoveryTimeoutSeconds: $this->defaultRecoveryTimeoutSeconds,
            halfOpenMaxCalls: $this->defaultHalfOpenMaxCalls,
        );
    }

    /**
     * Reset all circuit breakers to their initial state.
     *
     * This method resets every circuit breaker managed by this factory,
     * transitioning them all to CLOSED state and clearing their counters.
     */
    public function resetAll(): void
    {
        foreach ($this->circuitBreakers as $circuitBreaker) {
            $circuitBreaker->reset();
        }
    }

    /**
     * Reset a specific service's circuit breaker to its initial state.
     *
     * If no circuit breaker exists for the specified service, this method does nothing.
     *
     * @param string $serviceName The name of the service whose circuit breaker should be reset
     */
    public function resetService(string $serviceName): void
    {
        $circuitBreaker = $this->circuitBreakers[$serviceName] ?? null;
        if (null === $circuitBreaker) {
            return;
        }

        $this->circuitBreakers[$serviceName]->reset();
    }

    /**
     * Get all circuit breakers managed by this factory.
     *
     * @return array<string, CircuitBreakerInterface> Array of circuit breakers indexed by service name
     */
    public function circuitBreakers(): array
    {
        return $this->circuitBreakers;
    }
}
