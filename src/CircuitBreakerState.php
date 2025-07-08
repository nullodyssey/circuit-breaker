<?php

declare(strict_types=1);

namespace NullOdyssey\CircuitBreaker;

enum CircuitBreakerState: string
{
    case CLOSED = 'closed';
    case OPEN = 'open';
    case HALF_OPEN = 'half_open';

    public function isClosed(): bool
    {
        return self::CLOSED === $this;
    }

    public function isOpen(): bool
    {
        return self::OPEN === $this;
    }

    public function isHalfOpen(): bool
    {
        return self::HALF_OPEN === $this;
    }
}
