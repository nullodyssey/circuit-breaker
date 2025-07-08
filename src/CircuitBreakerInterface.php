<?php

declare(strict_types=1);

namespace NullOdyssey\CircuitBreaker;

/**
 * Contract for Circuit Breaker implementations.
 *
 * Defines the interface that all circuit breaker implementations must follow,
 * providing methods for call execution, state checking, and manual control.
 *
 * @author NullOdyssey
 *
 * @since 1.0.0
 */
interface CircuitBreakerInterface
{
    /**
     * Execute a callback with circuit breaker protection.
     *
     * @param callable $callback The function to execute
     *
     * @throws CircuitBreakerException When the circuit is open and not ready for retry
     * @throws \Throwable              Any exception thrown by the callback
     *
     * @return mixed The result of the callback execution
     */
    public function call(callable $callback): mixed;

    /**
     * Check if the circuit breaker is in OPEN state.
     *
     * @return bool True if the circuit is open (calls will fail immediately)
     */
    public function isOpen(): bool;

    /**
     * Check if the circuit breaker is in CLOSED state.
     *
     * @return bool True if the circuit is closed (normal operation)
     */
    public function isClosed(): bool;

    /**
     * Check if the circuit breaker is in HALF_OPEN state.
     *
     * @return bool True if the circuit is half-open (testing service recovery)
     */
    public function isHalfOpen(): bool;

    /**
     * Get the current state of the circuit breaker.
     *
     * @return CircuitBreakerState The current state (CLOSED, OPEN, or HALF_OPEN)
     */
    public function getState(): CircuitBreakerState;

    /**
     * Record a successful call execution.
     *
     * Should reset failure counters and handle appropriate state transitions.
     */
    public function recordSuccess(): void;

    /**
     * Record a failed call execution.
     *
     * Should increment failure counters and handle appropriate state transitions.
     */
    public function recordFailure(): void;

    /**
     * Reset the circuit breaker to its initial state.
     *
     * Should transition to CLOSED state and clear all counters and timestamps.
     */
    public function reset(): void;

    /**
     * Get the current number of consecutive failures.
     *
     * @return int The number of failures since the last successful call
     */
    public function failureCount(): int;

    /**
     * Get the timestamp of the last failure.
     *
     * @return ?\DateTimeImmutable The time of the last failure, or null if no failures recorded
     */
    public function lastFailureTime(): ?\DateTimeImmutable;

    /**
     * Get the next time the circuit breaker will attempt to reset.
     *
     * @return ?\DateTimeImmutable The time when transition from OPEN to HALF_OPEN will be attempted, or null if not in OPEN state
     */
    public function nextAttemptTime(): ?\DateTimeImmutable;

    /**
     * Get the current number of calls made in half-open state.
     *
     * @return int The number of calls made since entering half-open state
     */
    public function halfOpenCallCount(): int;

    /**
     * Get the current number of successful calls made in half-open state.
     *
     * @return int The number of successful calls made since entering half-open state
     */
    public function halfOpenSuccessCount(): int;
}
