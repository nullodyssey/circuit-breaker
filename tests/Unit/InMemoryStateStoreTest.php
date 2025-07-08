<?php

declare(strict_types=1);

namespace NullOdyssey\CircuitBreaker\Tests\Unit;

use NullOdyssey\CircuitBreaker\CircuitBreakerState;
use NullOdyssey\CircuitBreaker\CircuitBreakerStateData;
use NullOdyssey\CircuitBreaker\InMemoryStore;
use PHPUnit\Framework\TestCase;

final class InMemoryStateStoreTest extends TestCase
{
    private InMemoryStore $stateStore;

    protected function setUp(): void
    {
        $this->stateStore = new InMemoryStore();
    }

    public function testLoadNonExistentService(): void
    {
        $result = $this->stateStore->load('non-existent');
        self::assertNull($result);
    }

    public function testSaveAndLoad(): void
    {
        $stateData = new CircuitBreakerStateData(
            state: CircuitBreakerState::OPEN,
            failureCount: 5,
            halfOpenCallCount: 0,
            halfOpenSuccessCount: 0,
            lastFailureTime: new \DateTimeImmutable(),
            nextAttemptTime: new \DateTimeImmutable('+60 seconds'),
            lastUpdated: new \DateTimeImmutable()
        );

        $this->stateStore->save('test-service', $stateData);
        $loaded = $this->stateStore->load('test-service');

        self::assertNotNull($loaded);
        self::assertSame(CircuitBreakerState::OPEN, $loaded->state);
        self::assertSame(5, $loaded->failureCount);
    }

    public function testExists(): void
    {
        self::assertFalse($this->stateStore->exists('test-service'));

        $stateData = new CircuitBreakerStateData(
            state: CircuitBreakerState::CLOSED,
            failureCount: 0,
            halfOpenCallCount: 0,
            halfOpenSuccessCount: 0,
            lastFailureTime: null,
            nextAttemptTime: null,
            lastUpdated: new \DateTimeImmutable()
        );

        $this->stateStore->save('test-service', $stateData);
        self::assertTrue($this->stateStore->exists('test-service'));
    }

    public function testDelete(): void
    {
        $stateData = new CircuitBreakerStateData(
            state: CircuitBreakerState::CLOSED,
            failureCount: 0,
            halfOpenCallCount: 0,
            halfOpenSuccessCount: 0,
            lastFailureTime: null,
            nextAttemptTime: null,
            lastUpdated: new \DateTimeImmutable()
        );

        $this->stateStore->save('test-service', $stateData);
        self::assertTrue($this->stateStore->exists('test-service'));

        $this->stateStore->delete('test-service');
        self::assertFalse($this->stateStore->exists('test-service'));
    }

    public function testClear(): void
    {
        $stateData1 = new CircuitBreakerStateData(
            state: CircuitBreakerState::CLOSED,
            failureCount: 0,
            halfOpenCallCount: 0,
            halfOpenSuccessCount: 0,
            lastFailureTime: null,
            nextAttemptTime: null,
            lastUpdated: new \DateTimeImmutable()
        );

        $stateData2 = new CircuitBreakerStateData(
            state: CircuitBreakerState::OPEN,
            failureCount: 5,
            halfOpenCallCount: 0,
            halfOpenSuccessCount: 0,
            lastFailureTime: new \DateTimeImmutable(),
            nextAttemptTime: new \DateTimeImmutable('+60 seconds'),
            lastUpdated: new \DateTimeImmutable()
        );

        $this->stateStore->save('service-1', $stateData1);
        $this->stateStore->save('service-2', $stateData2);

        self::assertTrue($this->stateStore->exists('service-1'));
        self::assertTrue($this->stateStore->exists('service-2'));

        $this->stateStore->clear();

        self::assertFalse($this->stateStore->exists('service-1'));
        self::assertFalse($this->stateStore->exists('service-2'));
    }

    public function testLockWithCallback(): void
    {
        $stateData = new CircuitBreakerStateData(
            state: CircuitBreakerState::CLOSED,
            failureCount: 2,
            halfOpenCallCount: 0,
            halfOpenSuccessCount: 0,
            lastFailureTime: new \DateTimeImmutable(),
            nextAttemptTime: null,
            lastUpdated: new \DateTimeImmutable()
        );

        $this->stateStore->save('test-service', $stateData);

        $result = $this->stateStore->lock('test-service', function (?CircuitBreakerStateData $currentState) {
            self::assertNotNull($currentState);
            self::assertSame(2, $currentState->failureCount);

            // Return updated state
            return new CircuitBreakerStateData(
                state: $currentState->state,
                failureCount: $currentState->failureCount + 1,
                halfOpenCallCount: $currentState->halfOpenCallCount,
                halfOpenSuccessCount: $currentState->halfOpenSuccessCount,
                lastFailureTime: $currentState->lastFailureTime,
                nextAttemptTime: $currentState->nextAttemptTime,
                lastUpdated: new \DateTimeImmutable()
            );
        });

        // Check that state was updated
        $updatedState = $this->stateStore->load('test-service');
        self::assertNotNull($updatedState);
        self::assertSame(3, $updatedState->failureCount);
    }

    public function testLockPreventsParallelAccess(): void
    {
        self::expectException(\RuntimeException::class);
        self::expectExceptionMessage('Service "test-service" is already locked');

        $this->stateStore->lock('test-service', function () {
            // Try to acquire lock again within the same callback
            $this->stateStore->lock('test-service', function () {
                return null;
            });

            return null;
        });
    }

    public function testLockWithNonStateDataReturn(): void
    {
        $result = $this->stateStore->lock('test-service', function () {
            return 'some result';
        });

        self::assertSame('some result', $result);
        self::assertNull($this->stateStore->load('test-service'));
    }
}
