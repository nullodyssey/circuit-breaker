<?php

declare(strict_types=1);

namespace NullOdyssey\CircuitBreaker;

interface CircuitBreakerInterface
{
    public function call(callable $callback): mixed;

    public function isOpen(): bool;

    public function isClosed(): bool;

    public function isHalfOpen(): bool;

    public function getState(): CircuitBreakerState;

    public function recordSuccess(): void;

    public function recordFailure(): void;

    public function reset(): void;

    public function failureCount(): int;

    public function lastFailureTime(): ?\DateTimeImmutable;

    public function nextAttemptTime(): ?\DateTimeImmutable;
}
