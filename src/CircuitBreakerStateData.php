<?php

declare(strict_types=1);

namespace NullOdyssey\CircuitBreaker;

/**
 * Data transfer object for circuit breaker state.
 *
 * This class encapsulates all the state information needed to persist
 * and restore a circuit breaker's current state across multiple processes
 * or workers in a distributed environment.
 *
 * @author NullOdyssey
 *
 * @since 1.1.0
 */
final readonly class CircuitBreakerStateData
{
    /**
     * Create a new circuit breaker state data object.
     *
     * @param CircuitBreakerState $state                Current state of the circuit breaker
     * @param int                 $failureCount         Number of consecutive failures
     * @param int                 $halfOpenCallCount    Number of calls made in half-open state
     * @param int                 $halfOpenSuccessCount Number of successful calls in half-open state
     * @param ?\DateTimeImmutable $lastFailureTime      Timestamp of the last failure
     * @param ?\DateTimeImmutable $nextAttemptTime      Next time to attempt reset from open to half-open
     * @param \DateTimeImmutable  $lastUpdated          Timestamp of when this state was last updated
     */
    public function __construct(
        public CircuitBreakerState $state,
        public int $failureCount,
        public int $halfOpenCallCount,
        public int $halfOpenSuccessCount,
        public ?\DateTimeImmutable $lastFailureTime,
        public ?\DateTimeImmutable $nextAttemptTime,
        public \DateTimeImmutable $lastUpdated,
    ) {
    }

    /**
     * Create state data from a CircuitBreaker instance.
     *
     * @param CircuitBreakerInterface $circuitBreaker The circuit breaker to extract state from
     *
     * @return self The state data object
     */
    public static function fromCircuitBreaker(CircuitBreakerInterface $circuitBreaker): self
    {
        return new self(
            state: $circuitBreaker->getState(),
            failureCount: $circuitBreaker->failureCount(),
            halfOpenCallCount: $circuitBreaker->halfOpenCallCount(),
            halfOpenSuccessCount: $circuitBreaker->halfOpenSuccessCount(),
            lastFailureTime: $circuitBreaker->lastFailureTime(),
            nextAttemptTime: $circuitBreaker->nextAttemptTime(),
            lastUpdated: new \DateTimeImmutable(),
        );
    }

    /**
     * Convert state data to an array for serialization.
     *
     * @return array<string, mixed> The state data as an associative array
     */
    public function toArray(): array
    {
        return [
            'state' => $this->state->value,
            'failure_count' => $this->failureCount,
            'half_open_call_count' => $this->halfOpenCallCount,
            'half_open_success_count' => $this->halfOpenSuccessCount,
            'last_failure_time' => $this->lastFailureTime?->format(\DateTimeInterface::ATOM),
            'next_attempt_time' => $this->nextAttemptTime?->format(\DateTimeInterface::ATOM),
            'last_updated' => $this->lastUpdated->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * Create state data from an array (for deserialization).
     *
     * @param array<string, mixed> $data The state data as an associative array
     *
     * @throws \InvalidArgumentException If the data is invalid
     *
     * @return self The state data object
     */
    public static function fromArray(array $data): self
    {
        $requiredFields = ['state', 'failure_count', 'half_open_call_count', 'half_open_success_count', 'last_updated'];
        foreach ($requiredFields as $field) {
            if (false === \array_key_exists($field, $data)) {
                throw new \InvalidArgumentException(\sprintf('Missing required field: %s', $field));
            }
        }

        return new self(
            state: CircuitBreakerState::from($data['state']),
            failureCount: (int) $data['failure_count'],
            halfOpenCallCount: (int) $data['half_open_call_count'],
            halfOpenSuccessCount: (int) $data['half_open_success_count'],
            lastFailureTime: null !== $data['last_failure_time'] ? new \DateTimeImmutable($data['last_failure_time']) : null,
            nextAttemptTime: null !== $data['next_attempt_time'] ? new \DateTimeImmutable($data['next_attempt_time']) : null,
            lastUpdated: new \DateTimeImmutable($data['last_updated']),
        );
    }
}
