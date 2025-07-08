<?php

declare(strict_types=1);

namespace NullOdyssey\CircuitBreaker;

interface CircuitBreakerFactoryInterface
{
    public function circuitFor(string $serviceName): CircuitBreakerInterface;

    public function resetAll(): void;

    public function resetService(string $serviceName): void;

    /**
     * @return array<string, CircuitBreakerInterface>
     */
    public function circuitBreakers(): array;
}
