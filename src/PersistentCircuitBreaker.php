<?php

declare(strict_types=1);

namespace NullOdyssey\CircuitBreaker;

/**
 * Persistent Circuit Breaker implementation that uses a state store for multi-worker support.
 *
 * This class extends the basic circuit breaker functionality to persist state
 * across multiple processes or workers using a state store. It ensures thread
 * safety through locking mechanisms and synchronizes state changes.
 *
 * @author NullOdyssey
 *
 * @since 1.1.0
 */
final class PersistentCircuitBreaker implements CircuitBreakerInterface
{
    private CircuitBreakerStoreInterface $stateStore;
    private CircuitBreaker $localCircuitBreaker;
    private string $serviceName;

    /**
     * Create a new Persistent Circuit Breaker instance.
     *
     * @param string                            $serviceName            Name of the service this circuit breaker protects
     * @param CircuitBreakerStoreInterface $stateStore             The state store for persistence
     * @param int                               $failureThreshold       Number of failures before opening the circuit (default: 5)
     * @param int                               $recoveryTimeoutSeconds Time in seconds before attempting to reset from open to half-open (default: 60)
     * @param int                               $halfOpenMaxCalls       Maximum number of calls allowed in half-open state before transitioning to closed (default: 3)
     */
    public function __construct(
        string                       $serviceName,
        CircuitBreakerStoreInterface $stateStore,
        int                          $failureThreshold = 5,
        int                          $recoveryTimeoutSeconds = 60,
        int                          $halfOpenMaxCalls = 3,
    ) {
        $this->serviceName = $serviceName;
        $this->stateStore = $stateStore;
        $this->localCircuitBreaker = new CircuitBreaker(
            $serviceName,
            $failureThreshold,
            $recoveryTimeoutSeconds,
            $halfOpenMaxCalls
        );

        $this->loadState();
    }

    public function call(callable $callback): mixed
    {
        return $this->stateStore->lock($this->serviceName, function (?CircuitBreakerStateData $currentState) use ($callback) {
            // Load the current state into our local circuit breaker
            if (null !== $currentState) {
                $this->applyStateData($currentState);
            }

            // Execute the call using the local circuit breaker
            $result = $this->localCircuitBreaker->call($callback);

            // Save the updated state
            $this->saveState();

            return $result;
        });
    }

    public function isOpen(): bool
    {
        $this->loadState();

        return $this->localCircuitBreaker->isOpen();
    }

    public function isClosed(): bool
    {
        $this->loadState();

        return $this->localCircuitBreaker->isClosed();
    }

    public function isHalfOpen(): bool
    {
        $this->loadState();

        return $this->localCircuitBreaker->isHalfOpen();
    }

    public function getState(): CircuitBreakerState
    {
        $this->loadState();

        return $this->localCircuitBreaker->getState();
    }

    public function recordSuccess(): void
    {
        $this->stateStore->lock($this->serviceName, function (?CircuitBreakerStateData $currentState) {
            if (null !== $currentState) {
                $this->applyStateData($currentState);
            }

            $this->localCircuitBreaker->recordSuccess();
            $this->saveState();

            return null;
        });
    }

    public function recordFailure(): void
    {
        $this->stateStore->lock($this->serviceName, function (?CircuitBreakerStateData $currentState) {
            if (null !== $currentState) {
                $this->applyStateData($currentState);
            }

            $this->localCircuitBreaker->recordFailure();
            $this->saveState();

            return null;
        });
    }

    public function reset(): void
    {
        $this->stateStore->lock($this->serviceName, function () {
            $this->localCircuitBreaker->reset();
            $this->saveState();

            return null;
        });
    }

    public function failureCount(): int
    {
        $this->loadState();

        return $this->localCircuitBreaker->failureCount();
    }

    public function lastFailureTime(): ?\DateTimeImmutable
    {
        $this->loadState();

        return $this->localCircuitBreaker->lastFailureTime();
    }

    public function nextAttemptTime(): ?\DateTimeImmutable
    {
        $this->loadState();

        return $this->localCircuitBreaker->nextAttemptTime();
    }

    public function halfOpenCallCount(): int
    {
        $this->loadState();

        return $this->localCircuitBreaker->halfOpenCallCount();
    }

    public function halfOpenSuccessCount(): int
    {
        $this->loadState();

        return $this->localCircuitBreaker->halfOpenSuccessCount();
    }

    /**
     * Load state from the state store into the local circuit breaker.
     */
    private function loadState(): void
    {
        $stateData = $this->stateStore->load($this->serviceName);
        if (null !== $stateData) {
            $this->applyStateData($stateData);
        }
    }

    /**
     * Save the current state of the local circuit breaker to the state store.
     */
    private function saveState(): void
    {
        $stateData = CircuitBreakerStateData::fromCircuitBreaker($this->localCircuitBreaker);
        $this->stateStore->save($this->serviceName, $stateData);
    }

    /**
     * Apply state data to the local circuit breaker.
     *
     * @param CircuitBreakerStateData $stateData The state data to apply
     */
    private function applyStateData(CircuitBreakerStateData $stateData): void
    {
        $this->localCircuitBreaker->restoreState($stateData);
    }
}
