<?php

declare(strict_types=1);

namespace NullOdyssey\CircuitBreaker\Tests\Unit;

use NullOdyssey\CircuitBreaker\CircuitBreakerState;
use PHPUnit\Framework\TestCase;

final class CircuitBreakerStateTest extends TestCase
{
    public function testClosedState(): void
    {
        $state = CircuitBreakerState::CLOSED;

        self::assertTrue($state->isClosed());
        self::assertFalse($state->isOpen());
        self::assertFalse($state->isHalfOpen());
        self::assertSame('closed', $state->value);
    }

    public function testOpenState(): void
    {
        $state = CircuitBreakerState::OPEN;

        self::assertFalse($state->isClosed());
        self::assertTrue($state->isOpen());
        self::assertFalse($state->isHalfOpen());
        self::assertSame('open', $state->value);
    }

    public function testHalfOpenState(): void
    {
        $state = CircuitBreakerState::HALF_OPEN;

        self::assertFalse($state->isClosed());
        self::assertFalse($state->isOpen());
        self::assertTrue($state->isHalfOpen());
        self::assertSame('half_open', $state->value);
    }
}
