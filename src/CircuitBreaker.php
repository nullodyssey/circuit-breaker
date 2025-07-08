<?php

declare(strict_types=1);

namespace NullOdyssey\CircuitBreaker;

final class CircuitBreaker implements CircuitBreakerInterface
{
    private CircuitBreakerState $state = CircuitBreakerState::CLOSED;
    private int $failureCount = 0;
    private int $halfOpenCallCount = 0;
    private int $halfOpenSuccessCount = 0;
    private ?\DateTimeImmutable $lastFailureTime = null;
    private ?\DateTimeImmutable $nextAttemptTime = null;

    public function __construct(
        private readonly string $serviceName,
        private readonly int $failureThreshold = 5,
        private readonly int $recoveryTimeoutSeconds = 60,
        private readonly int $halfOpenMaxCalls = 3,
    ) {
    }

    public function call(callable $callback): mixed
    {
        if (true === $this->isOpen()) {
            if (true === $this->shouldAttemptReset()) {
                $this->transitionToHalfOpen();
            } else {
                throw new CircuitBreakerException($this->serviceName, $this->state);
            }
        }

        if (true === $this->isHalfOpen() && $this->halfOpenCallCount >= $this->halfOpenMaxCalls) {
            throw new CircuitBreakerException($this->serviceName, $this->state);
        }

        if (true === $this->isHalfOpen()) {
            ++$this->halfOpenCallCount;
        }

        try {
            $result = $callback();
            $this->recordSuccess();

            return $result;
        } catch (\Throwable $e) {
            $this->recordFailure();
            throw $e;
        }
    }

    public function isOpen(): bool
    {
        return $this->state->isOpen();
    }

    public function isClosed(): bool
    {
        return $this->state->isClosed();
    }

    public function isHalfOpen(): bool
    {
        return $this->state->isHalfOpen();
    }

    public function getState(): CircuitBreakerState
    {
        return $this->state;
    }

    public function recordSuccess(): void
    {
        $this->failureCount = 0;
        $this->lastFailureTime = null;

        if (true === $this->isHalfOpen()) {
            ++$this->halfOpenSuccessCount;
            if ($this->halfOpenSuccessCount >= $this->halfOpenMaxCalls) {
                $this->transitionToClosed();
            }
        } elseif (true === $this->isOpen()) {
            $this->transitionToClosed();
        }
    }

    public function recordFailure(): void
    {
        ++$this->failureCount;
        $this->lastFailureTime = new \DateTimeImmutable();

        if (true === $this->isHalfOpen()) {
            $this->transitionToOpen();
        } elseif (true === $this->isClosed() && $this->failureCount >= $this->failureThreshold) {
            $this->transitionToOpen();
        }
    }

    public function reset(): void
    {
        $this->state = CircuitBreakerState::CLOSED;
        $this->failureCount = 0;
        $this->halfOpenCallCount = 0;
        $this->halfOpenSuccessCount = 0;
        $this->lastFailureTime = null;
        $this->nextAttemptTime = null;
    }

    private function shouldAttemptReset(): bool
    {
        if (null === $this->nextAttemptTime) {
            return true;
        }

        return new \DateTimeImmutable() >= $this->nextAttemptTime;
    }

    private function transitionToOpen(): void
    {
        $this->state = CircuitBreakerState::OPEN;
        $this->nextAttemptTime = new \DateTimeImmutable(
            \sprintf('+%d seconds', $this->recoveryTimeoutSeconds)
        );
    }

    private function transitionToHalfOpen(): void
    {
        $this->state = CircuitBreakerState::HALF_OPEN;
        $this->failureCount = 0;
        $this->halfOpenCallCount = 0;
        $this->halfOpenSuccessCount = 0;
    }

    private function transitionToClosed(): void
    {
        $this->state = CircuitBreakerState::CLOSED;
        $this->halfOpenCallCount = 0;
        $this->halfOpenSuccessCount = 0;
        $this->nextAttemptTime = null;
    }

    public function failureCount(): int
    {
        return $this->failureCount;
    }

    public function lastFailureTime(): ?\DateTimeImmutable
    {
        return $this->lastFailureTime;
    }

    public function nextAttemptTime(): ?\DateTimeImmutable
    {
        return $this->nextAttemptTime;
    }
}
