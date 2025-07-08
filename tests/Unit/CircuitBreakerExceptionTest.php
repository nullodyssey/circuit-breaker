<?php

declare(strict_types=1);

namespace NullOdyssey\CircuitBreaker\Tests\Unit;

use NullOdyssey\CircuitBreaker\CircuitBreakerException;
use NullOdyssey\CircuitBreaker\CircuitBreakerState;
use PHPUnit\Framework\TestCase;

final class CircuitBreakerExceptionTest extends TestCase
{
    public function testExceptionMessageWithClosedState(): void
    {
        $exception = new CircuitBreakerException('test-service', CircuitBreakerState::CLOSED);

        self::assertSame('Circuit breaker for service "test-service" is closed', $exception->getMessage());
    }

    public function testExceptionMessageWithOpenState(): void
    {
        $exception = new CircuitBreakerException('test-service', CircuitBreakerState::OPEN);

        self::assertSame('Circuit breaker for service "test-service" is open', $exception->getMessage());
    }

    public function testExceptionMessageWithHalfOpenState(): void
    {
        $exception = new CircuitBreakerException('test-service', CircuitBreakerState::HALF_OPEN);

        self::assertSame('Circuit breaker for service "test-service" is half_open', $exception->getMessage());
    }

    public function testExceptionMessageWithDifferentServiceName(): void
    {
        $exception = new CircuitBreakerException('payment-service', CircuitBreakerState::OPEN);

        self::assertSame('Circuit breaker for service "payment-service" is open', $exception->getMessage());
    }
}
