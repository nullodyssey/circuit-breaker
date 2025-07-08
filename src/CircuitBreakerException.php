<?php

declare(strict_types=1);

namespace NullOdyssey\CircuitBreaker;

class CircuitBreakerException extends \RuntimeException
{
    public function __construct(string $serviceName, CircuitBreakerState $state)
    {
        parent::__construct(
            \sprintf('Circuit breaker for service "%s" is %s', $serviceName, $state->value)
        );
    }
}
