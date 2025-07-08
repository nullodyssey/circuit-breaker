<?php

declare(strict_types=1);

namespace NullOdyssey\CircuitBreaker;

/**
 * In-memory implementation of CircuitBreakerStateStoreInterface.
 *
 * This implementation stores circuit breaker state in memory and provides
 * basic locking through a simple mutex mechanism. It's suitable for
 * single-process applications or testing, but not for multi-process
 * distributed environments.
 *
 * @author NullOdyssey
 *
 * @since 1.1.0
 */
final class InMemoryStore implements CircuitBreakerStoreInterface
{
    /**
     * @var array<string, CircuitBreakerStateData> In-memory state storage
     */
    private array $states = [];

    /**
     * @var array<string, bool> Simple lock mechanism
     */
    private array $locks = [];

    public function load(string $serviceName): ?CircuitBreakerStateData
    {
        return $this->states[$serviceName] ?? null;
    }

    public function save(string $serviceName, CircuitBreakerStateData $stateData): void
    {
        $this->states[$serviceName] = $stateData;
    }

    public function lock(string $serviceName, callable $callback): mixed
    {
        // Simple lock mechanism - in a real distributed environment,
        // this would need to use actual distributed locks
        if (isset($this->locks[$serviceName])) {
            throw new \RuntimeException(\sprintf('Service "%s" is already locked', $serviceName));
        }

        $this->locks[$serviceName] = true;

        try {
            $currentState = $this->load($serviceName);
            $result = $callback($currentState);

            // If callback returns state data, save it
            if ($result instanceof CircuitBreakerStateData) {
                $this->save($serviceName, $result);
            }

            return $result;
        } finally {
            unset($this->locks[$serviceName]);
        }
    }

    public function delete(string $serviceName): void
    {
        unset($this->states[$serviceName]);
    }

    public function exists(string $serviceName): bool
    {
        return \array_key_exists($serviceName, $this->states);
    }

    public function clear(): void
    {
        $this->states = [];
        $this->locks = [];
    }
}
