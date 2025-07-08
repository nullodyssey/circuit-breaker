<?php

declare(strict_types=1);

namespace NullOdyssey\CircuitBreaker;

class CircuitBreakerFactory implements CircuitBreakerFactoryInterface
{
    /**
     * @var array<string, CircuitBreakerInterface>
     */
    private array $circuitBreakers = [];

    public function __construct(
        private readonly int $defaultFailureThreshold = 5,
        private readonly int $defaultRecoveryTimeoutSeconds = 60,
        private readonly int $defaultHalfOpenMaxCalls = 3,
    ) {
    }

    public function circuitFor(string $serviceName): CircuitBreakerInterface
    {
        $circuitBreaker = $this->circuitBreakers[$serviceName] ?? null;
        if (null === $circuitBreaker) {
            $this->circuitBreakers[$serviceName] = new CircuitBreaker(
                serviceName: $serviceName,
                failureThreshold: $this->defaultFailureThreshold,
                recoveryTimeoutSeconds: $this->defaultRecoveryTimeoutSeconds,
                halfOpenMaxCalls: $this->defaultHalfOpenMaxCalls,
            );
        }

        return $this->circuitBreakers[$serviceName];
    }

    public function resetAll(): void
    {
        foreach ($this->circuitBreakers as $circuitBreaker) {
            $circuitBreaker->reset();
        }
    }

    public function resetService(string $serviceName): void
    {
        $circuitBreaker = $this->circuitBreakers[$serviceName] ?? null;
        if (null === $circuitBreaker) {
            return;
        }

        $this->circuitBreakers[$serviceName]->reset();
    }

    /**
     * @return array<string, CircuitBreakerInterface>
     */
    public function circuitBreakers(): array
    {
        return $this->circuitBreakers;
    }
}
