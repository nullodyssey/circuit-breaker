<?php

declare(strict_types=1);

namespace NullOdyssey\CircuitBreaker;

/**
 * Interface for persisting circuit breaker state across multiple workers/processes.
 *
 * This interface allows circuit breaker state to be shared between multiple
 * processes or workers, enabling distributed circuit breaker functionality.
 * Implementations should provide atomic operations and handle concurrent access.
 *
 * @author NullOdyssey
 *
 * @since 1.1.0
 */
interface CircuitBreakerStoreInterface
{
    /**
     * Load the circuit breaker state for a given service.
     *
     * @param string $serviceName The name of the service
     *
     * @return CircuitBreakerStateData|null The state data, or null if not found
     */
    public function load(string $serviceName): ?CircuitBreakerStateData;

    /**
     * Save the circuit breaker state for a given service.
     *
     * @param string                  $serviceName The name of the service
     * @param CircuitBreakerStateData $stateData   The state data to save
     */
    public function save(string $serviceName, CircuitBreakerStateData $stateData): void;

    /**
     * Execute a callback with exclusive lock on the service's state.
     *
     * This method ensures thread safety by acquiring a lock before executing
     * the callback and releasing it afterwards. The callback receives the
     * current state data and should return the updated state data.
     *
     * @param string   $serviceName The name of the service to lock
     * @param callable $callback    Function that receives and returns CircuitBreakerStateData|null
     *
     * @return mixed The result of the callback execution
     */
    public function lock(string $serviceName, callable $callback): mixed;

    /**
     * Delete the circuit breaker state for a given service.
     *
     * @param string $serviceName The name of the service
     */
    public function delete(string $serviceName): void;

    /**
     * Check if state exists for a given service.
     *
     * @param string $serviceName The name of the service
     *
     * @return bool True if state exists, false otherwise
     */
    public function exists(string $serviceName): bool;

    /**
     * Clear all stored circuit breaker states.
     */
    public function clear(): void;
}
