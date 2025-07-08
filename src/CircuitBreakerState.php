<?php

declare(strict_types=1);

namespace NullOdyssey\CircuitBreaker;

/**
 * Represents the possible states of a Circuit Breaker.
 *
 * The Circuit Breaker pattern uses three states to manage service reliability:
 * - CLOSED: Normal operation, calls pass through to the service
 * - OPEN: Service is considered down, calls fail immediately without trying the service
 * - HALF_OPEN: Testing mode to check if the service has recovered
 *
 * @author NullOdyssey
 *
 * @since 1.0.0
 */
enum CircuitBreakerState: string
{
    /** Normal operation state - calls pass through to the service */
    case CLOSED = 'closed';

    /** Failure state - calls fail immediately without trying the service */
    case OPEN = 'open';

    /** Testing state - limited calls allowed to test service recovery */
    case HALF_OPEN = 'half_open';

    /**
     * Check if the circuit breaker is in CLOSED state.
     *
     * @return bool True if in normal operation mode
     */
    public function isClosed(): bool
    {
        return self::CLOSED === $this;
    }

    /**
     * Check if the circuit breaker is in OPEN state.
     *
     * @return bool True if in failure mode (calls will be rejected)
     */
    public function isOpen(): bool
    {
        return self::OPEN === $this;
    }

    /**
     * Check if the circuit breaker is in HALF_OPEN state.
     *
     * @return bool True if in testing mode for service recovery
     */
    public function isHalfOpen(): bool
    {
        return self::HALF_OPEN === $this;
    }
}
