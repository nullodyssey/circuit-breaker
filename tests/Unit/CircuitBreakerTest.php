<?php

declare(strict_types=1);

namespace NullOdyssey\CircuitBreaker\Tests\Unit;

use NullOdyssey\CircuitBreaker\CircuitBreaker;
use NullOdyssey\CircuitBreaker\CircuitBreakerException;
use NullOdyssey\CircuitBreaker\CircuitBreakerState;
use PHPUnit\Framework\TestCase;

final class CircuitBreakerTest extends TestCase
{
    private CircuitBreaker $circuitBreaker;

    protected function setUp(): void
    {
        $this->circuitBreaker = new CircuitBreaker('test-service');
    }

    public function testInitialStateIsClosed(): void
    {
        self::assertTrue($this->circuitBreaker->isClosed());
        self::assertFalse($this->circuitBreaker->isOpen());
        self::assertFalse($this->circuitBreaker->isHalfOpen());
        self::assertSame(CircuitBreakerState::CLOSED, $this->circuitBreaker->getState());
    }

    public function testSuccessfulCallInClosedState(): void
    {
        $callback = static fn () => 'success';
        $result = $this->circuitBreaker->call($callback);

        self::assertSame('success', $result);
        self::assertTrue($this->circuitBreaker->isClosed());
        self::assertSame(0, $this->circuitBreaker->failureCount());
    }

    public function testFailedCallInClosedState(): void
    {
        $callback = static function () {
            throw new \RuntimeException('Service failed');
        };

        self::expectException(\RuntimeException::class);
        self::expectExceptionMessage('Service failed');

        $this->circuitBreaker->call($callback);

        self::assertTrue($this->circuitBreaker->isClosed());
        self::assertSame(1, $this->circuitBreaker->failureCount());
        self::assertNotNull($this->circuitBreaker->lastFailureTime());
    }

    public function testTransitionToOpenAfterFailureThreshold(): void
    {
        $callback = static function () {
            throw new \RuntimeException('Service failed');
        };

        // Fail 4 times (threshold is 5)
        for ($i = 0; $i < 4; ++$i) {
            try {
                $this->circuitBreaker->call($callback);
            } catch (\RuntimeException) {
                // Expected
            }
        }

        self::assertTrue($this->circuitBreaker->isClosed());
        self::assertSame(4, $this->circuitBreaker->failureCount());

        // 5th failure should open the circuit
        try {
            $this->circuitBreaker->call($callback);
        } catch (\RuntimeException) {
            // Expected
        }

        self::assertTrue($this->circuitBreaker->isOpen());
        self::assertSame(5, $this->circuitBreaker->failureCount());
        self::assertNotNull($this->circuitBreaker->nextAttemptTime());
    }

    public function testCallFailsImmediatelyInOpenState(): void
    {
        // Force circuit to open
        $this->forceCircuitToOpen();

        $callback = static fn () => 'should not execute';

        self::expectException(CircuitBreakerException::class);
        self::expectExceptionMessage('Circuit breaker for service "test-service" is open');

        $this->circuitBreaker->call($callback);
    }

    public function testTransitionToHalfOpenAfterTimeout(): void
    {
        $circuitBreaker = new CircuitBreaker('test-service', 1, 0, 1); // 0 second timeout

        // Force circuit to open
        $callback = static function () {
            throw new \RuntimeException('Service failed');
        };

        try {
            $circuitBreaker->call($callback);
        } catch (\RuntimeException) {
            // Expected
        }

        self::assertTrue($circuitBreaker->isOpen());

        // Sleep briefly to ensure timeout passes
        usleep(1000);

        $successCallback = static fn () => 'success';
        $result = $circuitBreaker->call($successCallback);

        self::assertSame('success', $result);
        self::assertTrue($circuitBreaker->isClosed());
    }

    public function testHalfOpenStateWithLimitedCalls(): void
    {
        $circuitBreaker = new CircuitBreaker('test-service', 1, 0, 2); // max 2 calls in half-open

        // Force circuit to open
        $failCallback = static function () {
            throw new \RuntimeException('Service failed');
        };

        try {
            $circuitBreaker->call($failCallback);
        } catch (\RuntimeException) {
            // Expected
        }

        self::assertTrue($circuitBreaker->isOpen());

        // Sleep to allow transition to half-open
        usleep(1000);

        $successCallback = static fn () => 'success';

        // First call should succeed and keep circuit in half-open
        $result1 = $circuitBreaker->call($successCallback);
        self::assertSame('success', $result1);
        self::assertTrue($circuitBreaker->isHalfOpen());

        // Second call should succeed and transition to closed (reached max calls)
        $result2 = $circuitBreaker->call($successCallback);
        self::assertSame('success', $result2);
        self::assertTrue($circuitBreaker->isClosed());
    }

    public function testHalfOpenFailureReturnsToOpen(): void
    {
        $circuitBreaker = new CircuitBreaker('test-service', 1, 0, 2);

        // Force circuit to open
        $failCallback = static function () {
            throw new \RuntimeException('Service failed');
        };

        try {
            $circuitBreaker->call($failCallback);
        } catch (\RuntimeException) {
            // Expected
        }

        self::assertTrue($circuitBreaker->isOpen());

        // Sleep to allow transition to half-open
        usleep(1000);

        // First call fails in half-open state, should return to open
        self::expectException(\RuntimeException::class);

        try {
            $circuitBreaker->call($failCallback);
        } catch (\RuntimeException $e) {
            self::assertTrue($circuitBreaker->isOpen());
            throw $e;
        }
    }

    public function testRecordSuccessResetsFailureCount(): void
    {
        $callback = static function () {
            throw new \RuntimeException('Service failed');
        };

        // Record some failures
        for ($i = 0; $i < 3; ++$i) {
            try {
                $this->circuitBreaker->call($callback);
            } catch (\RuntimeException) {
                // Expected
            }
        }

        self::assertSame(3, $this->circuitBreaker->failureCount());

        // Record success
        $this->circuitBreaker->recordSuccess();

        self::assertSame(0, $this->circuitBreaker->failureCount());
        self::assertNull($this->circuitBreaker->lastFailureTime());
    }

    public function testReset(): void
    {
        // Force circuit to open
        $this->forceCircuitToOpen();

        self::assertTrue($this->circuitBreaker->isOpen());
        self::assertSame(5, $this->circuitBreaker->failureCount());
        self::assertNotNull($this->circuitBreaker->lastFailureTime());
        self::assertNotNull($this->circuitBreaker->nextAttemptTime());

        // Reset circuit
        $this->circuitBreaker->reset();

        self::assertTrue($this->circuitBreaker->isClosed());
        self::assertSame(0, $this->circuitBreaker->failureCount());
        self::assertNull($this->circuitBreaker->lastFailureTime());
        self::assertNull($this->circuitBreaker->nextAttemptTime());
    }

    public function testHalfOpenMultipleSuccessfulCalls(): void
    {
        $circuitBreaker = new CircuitBreaker('test-service', 1, 0, 3); // max 3 calls in half-open

        // Force circuit to open
        $failCallback = static function () {
            throw new \RuntimeException('Service failed');
        };

        try {
            $circuitBreaker->call($failCallback);
        } catch (\RuntimeException) {
            // Expected
        }

        self::assertTrue($circuitBreaker->isOpen());

        // Sleep to allow transition to half-open
        usleep(1000);

        $successCallback = static fn () => 'success';

        // First successful call - should stay in half-open
        $result1 = $circuitBreaker->call($successCallback);
        self::assertSame('success', $result1);
        self::assertTrue($circuitBreaker->isHalfOpen());

        // Second successful call - should stay in half-open
        $result2 = $circuitBreaker->call($successCallback);
        self::assertSame('success', $result2);
        self::assertTrue($circuitBreaker->isHalfOpen());

        // Third successful call - should transition to closed (reached max)
        $result3 = $circuitBreaker->call($successCallback);
        self::assertSame('success', $result3);
        self::assertTrue($circuitBreaker->isClosed());
    }

    public function testHalfOpenMixedSuccessFailure(): void
    {
        $circuitBreaker = new CircuitBreaker('test-service', 1, 0, 3);

        // Force circuit to open
        $failCallback = static function () {
            throw new \RuntimeException('Service failed');
        };

        try {
            $circuitBreaker->call($failCallback);
        } catch (\RuntimeException) {
            // Expected
        }

        self::assertTrue($circuitBreaker->isOpen());

        // Sleep to allow transition to half-open
        usleep(1000);

        $successCallback = static fn () => 'success';

        // First successful call - should stay in half-open
        $result1 = $circuitBreaker->call($successCallback);
        self::assertSame('success', $result1);
        self::assertTrue($circuitBreaker->isHalfOpen());

        // Second call fails - should transition back to open
        self::expectException(\RuntimeException::class);

        try {
            $circuitBreaker->call($failCallback);
        } catch (\RuntimeException $e) {
            self::assertTrue($circuitBreaker->isOpen());
            throw $e;
        }
    }

    public function testCustomThresholds(): void
    {
        $circuitBreaker = new CircuitBreaker('test-service', 2, 30, 1);

        $callback = static function () {
            throw new \RuntimeException('Service failed');
        };

        // Should remain closed after 1 failure
        try {
            $circuitBreaker->call($callback);
        } catch (\RuntimeException) {
            // Expected
        }

        self::assertTrue($circuitBreaker->isClosed());

        // Should open after 2nd failure
        try {
            $circuitBreaker->call($callback);
        } catch (\RuntimeException) {
            // Expected
        }

        self::assertTrue($circuitBreaker->isOpen());
    }

    private function forceCircuitToOpen(): void
    {
        $callback = static function () {
            throw new \RuntimeException('Service failed');
        };

        for ($i = 0; $i < 5; ++$i) {
            try {
                $this->circuitBreaker->call($callback);
            } catch (\RuntimeException) {
                // Expected
            }
        }
    }
}
