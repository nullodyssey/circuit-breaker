<?php

declare(strict_types=1);

namespace NullOdyssey\CircuitBreaker\Tests\Unit;

use NullOdyssey\CircuitBreaker\CircuitBreaker;
use NullOdyssey\CircuitBreaker\CircuitBreakerFactory;
use NullOdyssey\CircuitBreaker\CircuitBreakerInterface;
use NullOdyssey\CircuitBreaker\InMemoryStore;
use NullOdyssey\CircuitBreaker\PersistentCircuitBreaker;
use PHPUnit\Framework\TestCase;

final class CircuitBreakerFactoryTest extends TestCase
{
    private CircuitBreakerFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new CircuitBreakerFactory();
    }

    public function testCircuitForCreatesNewCircuitBreaker(): void
    {
        $circuitBreaker = $this->factory->circuitFor('test-service');

        self::assertTrue($circuitBreaker->isClosed());
    }

    public function testCircuitForReturnsSameInstanceForSameService(): void
    {
        $circuitBreaker1 = $this->factory->circuitFor('test-service');
        $circuitBreaker2 = $this->factory->circuitFor('test-service');

        self::assertSame($circuitBreaker1, $circuitBreaker2);
    }

    public function testCircuitForReturnsDifferentInstancesForDifferentServices(): void
    {
        $circuitBreaker1 = $this->factory->circuitFor('service-1');
        $circuitBreaker2 = $this->factory->circuitFor('service-2');

        self::assertNotSame($circuitBreaker1, $circuitBreaker2);
    }

    public function testCircuitBreakersReturnsAllCreatedCircuitBreakers(): void
    {
        self::assertEmpty($this->factory->circuitBreakers());

        $circuitBreaker1 = $this->factory->circuitFor('service-1');
        $circuitBreaker2 = $this->factory->circuitFor('service-2');

        $circuitBreakers = $this->factory->circuitBreakers();

        self::assertCount(2, $circuitBreakers);
        self::assertSame($circuitBreaker1, $circuitBreakers['service-1']);
        self::assertSame($circuitBreaker2, $circuitBreakers['service-2']);
    }

    public function testResetServiceResetsSpecificCircuitBreaker(): void
    {
        $circuitBreaker = $this->factory->circuitFor('test-service');

        // Force some failures
        $this->forceFailures($circuitBreaker, 3);
        self::assertSame(3, $circuitBreaker->failureCount());

        // Reset specific service
        $this->factory->resetService('test-service');

        self::assertSame(0, $circuitBreaker->failureCount());
        self::assertTrue($circuitBreaker->isClosed());
    }

    public function testResetServiceWithNonExistentService(): void
    {
        // Should not throw exception
        $this->factory->resetService('non-existent-service');
        self::expectNotToPerformAssertions();
    }

    public function testResetAllResetsAllCircuitBreakers(): void
    {
        $circuitBreaker1 = $this->factory->circuitFor('service-1');
        $circuitBreaker2 = $this->factory->circuitFor('service-2');

        // Force failures on both
        $this->forceFailures($circuitBreaker1, 3);
        $this->forceFailures($circuitBreaker2, 2);

        self::assertSame(3, $circuitBreaker1->failureCount());
        self::assertSame(2, $circuitBreaker2->failureCount());

        // Reset all
        $this->factory->resetAll();

        self::assertSame(0, $circuitBreaker1->failureCount());
        self::assertSame(0, $circuitBreaker2->failureCount());
        self::assertTrue($circuitBreaker1->isClosed());
        self::assertTrue($circuitBreaker2->isClosed());
    }

    public function testCustomDefaultParameters(): void
    {
        $factory = new CircuitBreakerFactory(
            defaultFailureThreshold: 2,
            defaultRecoveryTimeoutSeconds: 30,
            defaultHalfOpenMaxCalls: 1
        );

        $circuitBreaker = $factory->circuitFor('test-service');

        // Test that custom threshold is used
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

        // Should open after 2nd failure (custom threshold)
        try {
            $circuitBreaker->call($callback);
        } catch (\RuntimeException) {
            // Expected
        }

        self::assertTrue($circuitBreaker->isOpen());
    }

    public function testMultipleServicesOperateIndependently(): void
    {
        $circuitBreaker1 = $this->factory->circuitFor('service-1');
        $circuitBreaker2 = $this->factory->circuitFor('service-2');

        // Force service-1 to open
        $this->forceFailures($circuitBreaker1, 5);
        self::assertTrue($circuitBreaker1->isOpen());

        // service-2 should remain closed
        self::assertTrue($circuitBreaker2->isClosed());

        // service-2 should still work
        $result = $circuitBreaker2->call(static fn () => 'success');
        self::assertSame('success', $result);
    }

    public function testFactoryWithStateStore(): void
    {
        $stateStore = new InMemoryStore();
        $factory = new CircuitBreakerFactory(
            defaultFailureThreshold: 2,
            defaultRecoveryTimeoutSeconds: 30,
            defaultHalfOpenMaxCalls: 1,
            stateStore: $stateStore
        );

        $circuitBreaker = $factory->circuitFor('test-service');

        // Should be PersistentCircuitBreaker when state store is provided
        self::assertInstanceOf(PersistentCircuitBreaker::class, $circuitBreaker);

        // Test that custom thresholds are used
        $circuitBreaker->recordFailure();
        self::assertTrue($circuitBreaker->isClosed());

        $circuitBreaker->recordFailure();
        self::assertTrue($circuitBreaker->isOpen());
    }

    public function testFactoryWithoutStateStore(): void
    {
        $factory = new CircuitBreakerFactory();
        $circuitBreaker = $factory->circuitFor('test-service');

        // Should be regular CircuitBreaker when no state store is provided
        self::assertInstanceOf(CircuitBreaker::class, $circuitBreaker);
    }

    private function forceFailures(CircuitBreakerInterface $circuitBreaker, int $count): void
    {
        for ($i = 0; $i < $count; ++$i) {
            $circuitBreaker->recordFailure();
        }
    }
}
