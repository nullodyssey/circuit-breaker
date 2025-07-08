<?php

declare(strict_types=1);

namespace NullOdyssey\CircuitBreaker\Tests\Unit;

use NullOdyssey\CircuitBreaker\CircuitBreakerException;
use NullOdyssey\CircuitBreaker\CircuitBreakerState;
use NullOdyssey\CircuitBreaker\InMemoryStore;
use NullOdyssey\CircuitBreaker\PersistentCircuitBreaker;
use PHPUnit\Framework\TestCase;

final class PersistentCircuitBreakerTest extends TestCase
{
    private InMemoryStore $stateStore;
    private PersistentCircuitBreaker $circuitBreaker;

    protected function setUp(): void
    {
        $this->stateStore = new InMemoryStore();
        $this->circuitBreaker = new PersistentCircuitBreaker(
            'test-service',
            $this->stateStore,
            failureThreshold: 3,
            recoveryTimeoutSeconds: 0, // 0 for immediate testing
            halfOpenMaxCalls: 2
        );
    }

    public function testInitialStateIsClosed(): void
    {
        self::assertTrue($this->circuitBreaker->isClosed());
        self::assertFalse($this->circuitBreaker->isOpen());
        self::assertFalse($this->circuitBreaker->isHalfOpen());
        self::assertSame(CircuitBreakerState::CLOSED, $this->circuitBreaker->getState());
    }

    public function testSuccessfulCall(): void
    {
        $callback = static fn () => 'success';
        $result = $this->circuitBreaker->call($callback);

        self::assertSame('success', $result);
        self::assertTrue($this->circuitBreaker->isClosed());
    }

    public function testFailedCall(): void
    {
        $callback = static function () {
            throw new \RuntimeException('Service failed');
        };

        self::expectException(\RuntimeException::class);
        self::expectExceptionMessage('Service failed');

        $this->circuitBreaker->call($callback);

        self::assertTrue($this->circuitBreaker->isClosed());
        self::assertSame(1, $this->circuitBreaker->failureCount());
    }

    public function testTransitionToOpenAfterFailureThreshold(): void
    {
        $callback = static function () {
            throw new \RuntimeException('Service failed');
        };

        // Fail 3 times to reach threshold
        for ($i = 0; $i < 3; ++$i) {
            try {
                $this->circuitBreaker->call($callback);
            } catch (\RuntimeException) {
                // Expected
            }
        }

        self::assertTrue($this->circuitBreaker->isOpen());
        self::assertSame(3, $this->circuitBreaker->failureCount());
    }

    public function testCallFailsImmediatelyInOpenState(): void
    {
        // Create a circuit breaker with normal timeout to test open state behavior
        $circuitBreaker = new PersistentCircuitBreaker(
            'test-service-open',
            $this->stateStore,
            failureThreshold: 3,
            recoveryTimeoutSeconds: 60, // Normal timeout
            halfOpenMaxCalls: 2
        );

        // Force circuit to open
        $failCallback = static function () {
            throw new \RuntimeException('Service failed');
        };

        for ($i = 0; $i < 3; ++$i) {
            try {
                $circuitBreaker->call($failCallback);
            } catch (\RuntimeException) {
                // Expected
            }
        }

        self::assertTrue($circuitBreaker->isOpen());

        $callback = static fn () => 'should not execute';

        self::expectException(CircuitBreakerException::class);
        self::expectExceptionMessage('Circuit breaker for service "test-service-open" is open');

        $circuitBreaker->call($callback);
    }

    public function testStateIsPersisted(): void
    {
        // Record failures
        $this->circuitBreaker->recordFailure();
        $this->circuitBreaker->recordFailure();

        self::assertSame(2, $this->circuitBreaker->failureCount());

        // Create new circuit breaker instance for same service
        $newCircuitBreaker = new PersistentCircuitBreaker(
            'test-service',
            $this->stateStore,
            failureThreshold: 3,
            recoveryTimeoutSeconds: 0,
            halfOpenMaxCalls: 2
        );

        // State should be restored
        self::assertSame(2, $newCircuitBreaker->failureCount());
        self::assertTrue($newCircuitBreaker->isClosed());
    }

    public function testMultipleServicesAreIndependent(): void
    {
        $circuitBreaker1 = new PersistentCircuitBreaker('service-1', $this->stateStore, 2);
        $circuitBreaker2 = new PersistentCircuitBreaker('service-2', $this->stateStore, 2);

        // Force service-1 to open
        $circuitBreaker1->recordFailure();
        $circuitBreaker1->recordFailure();

        self::assertTrue($circuitBreaker1->isOpen());
        self::assertTrue($circuitBreaker2->isClosed());

        // service-2 should still work
        $result = $circuitBreaker2->call(static fn () => 'success');
        self::assertSame('success', $result);
    }

    public function testReset(): void
    {
        // Force circuit to open
        $this->forceCircuitToOpen();

        self::assertTrue($this->circuitBreaker->isOpen());
        self::assertSame(3, $this->circuitBreaker->failureCount());

        // Reset circuit
        $this->circuitBreaker->reset();

        self::assertTrue($this->circuitBreaker->isClosed());
        self::assertSame(0, $this->circuitBreaker->failureCount());

        // State should be persisted
        $newCircuitBreaker = new PersistentCircuitBreaker(
            'test-service',
            $this->stateStore,
            failureThreshold: 3
        );

        self::assertTrue($newCircuitBreaker->isClosed());
        self::assertSame(0, $newCircuitBreaker->failureCount());
    }

    public function testHalfOpenRecovery(): void
    {
        // Force circuit to open
        $this->forceCircuitToOpen();
        self::assertTrue($this->circuitBreaker->isOpen());

        // Since recovery timeout is 0, next call should transition to half-open
        $successCallback = static fn () => 'success';

        // First successful call should transition to half-open
        $result = $this->circuitBreaker->call($successCallback);
        self::assertSame('success', $result);
        self::assertTrue($this->circuitBreaker->isHalfOpen());

        // Second successful call should transition to closed (halfOpenMaxCalls = 2)
        $result = $this->circuitBreaker->call($successCallback);
        self::assertSame('success', $result);
        self::assertTrue($this->circuitBreaker->isClosed());
    }

    private function forceCircuitToOpen(): void
    {
        $callback = static function () {
            throw new \RuntimeException('Service failed');
        };

        for ($i = 0; $i < 3; ++$i) {
            try {
                $this->circuitBreaker->call($callback);
            } catch (\RuntimeException) {
                // Expected
            }
        }
    }
}
