<?php

declare(strict_types=1);

namespace NullOdyssey\CircuitBreaker;

/**
 * Circuit Breaker implementation that provides fault tolerance for external service calls.
 *
 * The Circuit Breaker pattern prevents cascading failures by monitoring service calls
 * and temporarily disabling failing services. It operates in three states:
 * - CLOSED: Normal operation, calls pass through
 * - OPEN: Circuit is open, calls fail immediately without attempting the service
 * - HALF_OPEN: Limited testing mode to check if the service has recovered
 *
 * @author NullOdyssey
 *
 * @since 1.0.0
 */
final class CircuitBreaker implements CircuitBreakerInterface
{
    /** @var CircuitBreakerState Current state of the circuit breaker */
    private CircuitBreakerState $state = CircuitBreakerState::CLOSED;

    /** @var int Number of consecutive failures */
    private int $failureCount = 0;

    /** @var int Number of calls made in half-open state */
    private int $halfOpenCallCount = 0;

    /** @var int Number of successful calls made in half-open state */
    private int $halfOpenSuccessCount = 0;

    /** @var ?\DateTimeImmutable Timestamp of the last failure */
    private ?\DateTimeImmutable $lastFailureTime = null;

    /** @var ?\DateTimeImmutable Next time to attempt reset from open to half-open */
    private ?\DateTimeImmutable $nextAttemptTime = null;

    /**
     * Create a new Circuit Breaker instance.
     *
     * @param string $serviceName            Name of the service this circuit breaker protects
     * @param int    $failureThreshold       Number of failures before opening the circuit (default: 5)
     * @param int    $recoveryTimeoutSeconds Time in seconds before attempting to reset from open to half-open (default: 60)
     * @param int    $halfOpenMaxCalls       Maximum number of calls allowed in half-open state before transitioning to closed (default: 3)
     */
    public function __construct(
        private readonly string $serviceName,
        private readonly int $failureThreshold = 5,
        private readonly int $recoveryTimeoutSeconds = 60,
        private readonly int $halfOpenMaxCalls = 3,
    ) {
    }

    /**
     * Execute a callback with circuit breaker protection.
     *
     * This method handles the core circuit breaker logic:
     * - In CLOSED state: calls pass through normally
     * - In OPEN state: calls fail immediately unless timeout has passed
     * - In HALF_OPEN state: limited calls are allowed to test service recovery
     *
     * @param callable $callback The function to execute
     *
     * @throws CircuitBreakerException When the circuit is open and not ready for retry
     * @throws \Throwable              Any exception thrown by the callback
     *
     * @return mixed The result of the callback execution
     */
    public function call(callable $callback): mixed
    {
        if (true === $this->isOpen()) {
            if (true === $this->shouldAttemptReset()) {
                $this->transitionToHalfOpen();
            } else {
                throw new CircuitBreakerException($this->serviceName, $this->state);
            }
        }

        if (true === $this->isHalfOpen() && $this->halfOpenCallCount >= $this->halfOpenMaxCalls) {
            throw new CircuitBreakerException($this->serviceName, $this->state);
        }

        if (true === $this->isHalfOpen()) {
            ++$this->halfOpenCallCount;
        }

        try {
            $result = $callback();
            $this->recordSuccess();

            return $result;
        } catch (\Throwable $e) {
            $this->recordFailure();
            throw $e;
        }
    }

    /**
     * Check if the circuit breaker is in OPEN state.
     *
     * @return bool True if the circuit is open (calls will fail immediately)
     */
    public function isOpen(): bool
    {
        return $this->state->isOpen();
    }

    /**
     * Check if the circuit breaker is in CLOSED state.
     *
     * @return bool True if the circuit is closed (normal operation)
     */
    public function isClosed(): bool
    {
        return $this->state->isClosed();
    }

    /**
     * Check if the circuit breaker is in HALF_OPEN state.
     *
     * @return bool True if the circuit is half-open (testing service recovery)
     */
    public function isHalfOpen(): bool
    {
        return $this->state->isHalfOpen();
    }

    /**
     * Get the current state of the circuit breaker.
     *
     * @return CircuitBreakerState The current state (CLOSED, OPEN, or HALF_OPEN)
     */
    public function getState(): CircuitBreakerState
    {
        return $this->state;
    }

    /**
     * Record a successful call execution.
     *
     * Resets the failure count and handles state transitions:
     * - In HALF_OPEN: increments success count and transitions to CLOSED when max successes reached
     * - In OPEN: immediately transitions to CLOSED
     * - In CLOSED: no state change needed
     */
    public function recordSuccess(): void
    {
        $this->failureCount = 0;
        $this->lastFailureTime = null;

        if (true === $this->isHalfOpen()) {
            ++$this->halfOpenSuccessCount;
            if ($this->halfOpenSuccessCount >= $this->halfOpenMaxCalls) {
                $this->transitionToClosed();
            }
        } elseif (true === $this->isOpen()) {
            $this->transitionToClosed();
        }
    }

    /**
     * Record a failed call execution.
     *
     * Increments the failure count and handles state transitions:
     * - In HALF_OPEN: immediately transitions to OPEN
     * - In CLOSED: transitions to OPEN if failure threshold is reached
     * - In OPEN: no additional state change needed
     */
    public function recordFailure(): void
    {
        ++$this->failureCount;
        $this->lastFailureTime = new \DateTimeImmutable();

        if (true === $this->isHalfOpen()) {
            $this->transitionToOpen();
        } elseif (true === $this->isClosed() && $this->failureCount >= $this->failureThreshold) {
            $this->transitionToOpen();
        }
    }

    /**
     * Reset the circuit breaker to its initial state.
     *
     * Transitions to CLOSED state and clears all counters and timestamps.
     * This can be used for manual recovery or testing purposes.
     */
    public function reset(): void
    {
        $this->state = CircuitBreakerState::CLOSED;
        $this->failureCount = 0;
        $this->halfOpenCallCount = 0;
        $this->halfOpenSuccessCount = 0;
        $this->lastFailureTime = null;
        $this->nextAttemptTime = null;
    }

    private function shouldAttemptReset(): bool
    {
        if (null === $this->nextAttemptTime) {
            return true;
        }

        return new \DateTimeImmutable() >= $this->nextAttemptTime;
    }

    private function transitionToOpen(): void
    {
        $this->state = CircuitBreakerState::OPEN;
        $this->nextAttemptTime = new \DateTimeImmutable(
            \sprintf('+%d seconds', $this->recoveryTimeoutSeconds)
        );
    }

    private function transitionToHalfOpen(): void
    {
        $this->state = CircuitBreakerState::HALF_OPEN;
        $this->failureCount = 0;
        $this->halfOpenCallCount = 0;
        $this->halfOpenSuccessCount = 0;
    }

    private function transitionToClosed(): void
    {
        $this->state = CircuitBreakerState::CLOSED;
        $this->halfOpenCallCount = 0;
        $this->halfOpenSuccessCount = 0;
        $this->nextAttemptTime = null;
    }

    /**
     * Get the current number of consecutive failures.
     *
     * @return int The number of failures since the last successful call
     */
    public function failureCount(): int
    {
        return $this->failureCount;
    }

    /**
     * Get the timestamp of the last failure.
     *
     * @return ?\DateTimeImmutable The time of the last failure, or null if no failures recorded
     */
    public function lastFailureTime(): ?\DateTimeImmutable
    {
        return $this->lastFailureTime;
    }

    /**
     * Get the next time the circuit breaker will attempt to reset.
     *
     * @return ?\DateTimeImmutable The time when transition from OPEN to HALF_OPEN will be attempted, or null if not in OPEN state
     */
    public function nextAttemptTime(): ?\DateTimeImmutable
    {
        return $this->nextAttemptTime;
    }
}
