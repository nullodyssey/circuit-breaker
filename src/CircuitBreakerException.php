<?php

declare(strict_types=1);

namespace NullOdyssey\CircuitBreaker;

/**
 * Exception thrown when a circuit breaker prevents a call from being executed.
 *
 * This exception is thrown when the circuit breaker is in OPEN state and
 * rejects calls to prevent cascading failures. It provides information
 * about which service and state caused the rejection.
 *
 * @author NullOdyssey
 *
 * @since 1.0.0
 */
class CircuitBreakerException extends \RuntimeException
{
    /**
     * Create a new Circuit Breaker Exception.
     *
     * @param string              $serviceName The name of the service that was rejected
     * @param CircuitBreakerState $state       The current state of the circuit breaker
     */
    public function __construct(string $serviceName, CircuitBreakerState $state)
    {
        parent::__construct(
            \sprintf('Circuit breaker for service "%s" is %s', $serviceName, $state->value)
        );
    }
}
