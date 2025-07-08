<?php

declare(strict_types=1);

namespace NullOdyssey\CircuitBreaker\Tests\Unit;

use NullOdyssey\CircuitBreaker\CircuitBreaker;
use NullOdyssey\CircuitBreaker\CircuitBreakerState;
use NullOdyssey\CircuitBreaker\CircuitBreakerStateData;
use PHPUnit\Framework\TestCase;

final class CircuitBreakerStateDataTest extends TestCase
{
    public function testFromCircuitBreaker(): void
    {
        $circuitBreaker = new CircuitBreaker('test-service', 3, 30, 2);

        // Record some failures
        $circuitBreaker->recordFailure();
        $circuitBreaker->recordFailure();

        $stateData = CircuitBreakerStateData::fromCircuitBreaker($circuitBreaker);

        self::assertSame(CircuitBreakerState::CLOSED, $stateData->state);
        self::assertSame(2, $stateData->failureCount);
        self::assertSame(0, $stateData->halfOpenCallCount);
        self::assertSame(0, $stateData->halfOpenSuccessCount);
        self::assertNotNull($stateData->lastFailureTime);
        self::assertNull($stateData->nextAttemptTime);
    }

    public function testToArray(): void
    {
        $now = new \DateTimeImmutable();
        $failureTime = new \DateTimeImmutable('-1 hour');

        $stateData = new CircuitBreakerStateData(
            state: CircuitBreakerState::OPEN,
            failureCount: 5,
            halfOpenCallCount: 0,
            halfOpenSuccessCount: 0,
            lastFailureTime: $failureTime,
            nextAttemptTime: $now,
            lastUpdated: $now
        );

        $array = $stateData->toArray();

        self::assertSame('open', $array['state']);
        self::assertSame(5, $array['failure_count']);
        self::assertSame(0, $array['half_open_call_count']);
        self::assertSame(0, $array['half_open_success_count']);
        self::assertSame($failureTime->format(\DateTimeInterface::ATOM), $array['last_failure_time']);
        self::assertSame($now->format(\DateTimeInterface::ATOM), $array['next_attempt_time']);
        self::assertSame($now->format(\DateTimeInterface::ATOM), $array['last_updated']);
    }

    public function testFromArray(): void
    {
        $now = new \DateTimeImmutable();
        $failureTime = new \DateTimeImmutable('-1 hour');

        $array = [
            'state' => 'half_open',
            'failure_count' => 3,
            'half_open_call_count' => 1,
            'half_open_success_count' => 0,
            'last_failure_time' => $failureTime->format(\DateTimeInterface::ATOM),
            'next_attempt_time' => $now->format(\DateTimeInterface::ATOM),
            'last_updated' => $now->format(\DateTimeInterface::ATOM),
        ];

        $stateData = CircuitBreakerStateData::fromArray($array);

        self::assertSame(CircuitBreakerState::HALF_OPEN, $stateData->state);
        self::assertSame(3, $stateData->failureCount);
        self::assertSame(1, $stateData->halfOpenCallCount);
        self::assertSame(0, $stateData->halfOpenSuccessCount);
        self::assertNotNull($stateData->lastFailureTime);
        self::assertNotNull($stateData->nextAttemptTime);
        self::assertSame($failureTime->format(\DateTimeInterface::ATOM), $stateData->lastFailureTime->format(\DateTimeInterface::ATOM));
        self::assertSame($now->format(\DateTimeInterface::ATOM), $stateData->nextAttemptTime->format(\DateTimeInterface::ATOM));
        self::assertSame($now->format(\DateTimeInterface::ATOM), $stateData->lastUpdated->format(\DateTimeInterface::ATOM));
    }

    public function testFromArrayWithNullValues(): void
    {
        $now = new \DateTimeImmutable();

        $array = [
            'state' => 'closed',
            'failure_count' => 0,
            'half_open_call_count' => 0,
            'half_open_success_count' => 0,
            'last_failure_time' => null,
            'next_attempt_time' => null,
            'last_updated' => $now->format(\DateTimeInterface::ATOM),
        ];

        $stateData = CircuitBreakerStateData::fromArray($array);

        self::assertSame(CircuitBreakerState::CLOSED, $stateData->state);
        self::assertSame(0, $stateData->failureCount);
        self::assertNull($stateData->lastFailureTime);
        self::assertNull($stateData->nextAttemptTime);
    }

    public function testFromArrayMissingRequiredField(): void
    {
        $array = [
            'state' => 'closed',
            'failure_count' => 0,
            // Missing 'half_open_call_count'
            'half_open_success_count' => 0,
            'last_updated' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ];

        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('Missing required field: half_open_call_count');

        CircuitBreakerStateData::fromArray($array);
    }

    public function testFromArrayInvalidState(): void
    {
        $array = [
            'state' => 'invalid_state',
            'failure_count' => 0,
            'half_open_call_count' => 0,
            'half_open_success_count' => 0,
            'last_updated' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ];

        self::expectException(\ValueError::class);

        CircuitBreakerStateData::fromArray($array);
    }
}
