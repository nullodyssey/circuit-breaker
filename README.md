# Circuit Breaker Pattern Implementation for PHP

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.1-blue.svg)](https://www.php.net/)

A robust and feature-rich implementation of the Circuit Breaker pattern for PHP applications. This library helps prevent cascading failures by monitoring service calls and automatically switching between three states: **Closed**, **Open**, and **Half-Open**.

## 📋 Table of Contents

- [Features](#-features)
- [Installation](#-installation)
- [Quick Start](#-quick-start)
  - [Basic Usage](#basic-usage)
  - [Using the Factory Pattern](#using-the-factory-pattern)
  - [Multi-Worker/Distributed Setup](#multi-workerdistributed-setup)
- [Configuration](#-configuration)
  - [Circuit Breaker Parameters](#circuit-breaker-parameters)
  - [State Store Interface](#state-store-interface)
- [Symfony Integration](#-symfony-integration)
  - [Redis State Store](#redis-state-store)
  - [Service Configuration](#service-configuration)
  - [Usage in Controllers](#usage-in-controllers)
- [Circuit Breaker States](#-circuit-breaker-states)
- [State Transitions](#-state-transitions)
- [Monitoring & Metrics](#-monitoring--metrics)
- [Architecture](#-architecture)
- [Thread Safety](#-thread-safety)
- [Roadmap & Future Features](#-roadmap)
- [Contributing](#-contributing)
- [License](#-license)
- [Further Reading](#-further-reading)

## 🚀 Features

- **Three-State Circuit Breaker**: Implements the classic Closed → Open → Half-Open → Closed cycle
- **Multi-Worker Support**: Persistent state storage for distributed environments
- **Thread Safety**: Built-in locking mechanisms for concurrent access
- **Flexible Configuration**: Customizable failure thresholds, timeouts, and recovery settings
- **Factory Pattern**: Centralized management of multiple circuit breakers
- **Type Safety**: Full PHP 8.1+ type declarations and PHPStan level 8 compliance
- **Zero Dependencies**: No external runtime dependencies

## 📦 Installation

```bash
git clone git@github.com:nullodyssey/circuit-breaker.git
```

## 🎯 Quick Start

### Basic Usage

```php
<?php

use NullOdyssey\CircuitBreaker\CircuitBreaker;
use NullOdyssey\CircuitBreaker\CircuitBreakerException;

// Create a circuit breaker for your service
$circuitBreaker = new CircuitBreaker(
    serviceName: 'external-api',
    failureThreshold: 5,           // Open after 5 failures
    recoveryTimeoutSeconds: 60,    // Wait 60 seconds before trying again
    halfOpenMaxCalls: 3            // Allow 3 test calls in half-open state
);

// Wrap your service calls
try {
    $result = $circuitBreaker->call(function () {
        // Your potentially failing service call
        return file_get_contents('https://api.example.com/data');
    });
    
    echo "Success: " . $result;
} catch (CircuitBreakerException $e) {
    echo "Circuit breaker is open: " . $e->getMessage();
} catch (Exception $e) {
    echo "Service call failed: " . $e->getMessage();
}
```

### Using the Factory Pattern

```php
<?php

use NullOdyssey\CircuitBreaker\CircuitBreakerFactory;

// Create a factory with default settings
$factory = new CircuitBreakerFactory(
    defaultFailureThreshold: 5,
    defaultRecoveryTimeoutSeconds: 60,
    defaultHalfOpenMaxCalls: 3
);

// Get circuit breakers for different services
$userServiceCB = $factory->circuitFor('user-service');
$paymentServiceCB = $factory->circuitFor('payment-service');

// Each service has its own independent circuit breaker
$userServiceCB->call(fn() => $userApi->getUser($id));
$paymentServiceCB->call(fn() => $paymentApi->processPayment($data));
```

### Multi-Worker/Distributed Setup

For applications running across multiple processes or workers, use the persistent circuit breaker:

```php
<?php

use NullOdyssey\CircuitBreaker\CircuitBreakerFactory;
use NullOdyssey\CircuitBreaker\InMemoryStore;

// Create a state store (implement your own for Redis, Database, etc.)
$stateStore = new InMemoryStore();

// Create factory with state store
$factory = new CircuitBreakerFactory(
    defaultFailureThreshold: 5,
    defaultRecoveryTimeoutSeconds: 60,
    defaultHalfOpenMaxCalls: 3,
    stateStore: $stateStore  // Enable persistence
);

// Circuit breaker state is now shared across all workers
$circuitBreaker = $factory->circuitFor('shared-service');
```

## 🔧 Configuration

### Circuit Breaker Parameters

| Parameter | Description | Default |
|-----------|-------------|---------|
| `serviceName` | Unique identifier for the service | Required |
| `failureThreshold` | Number of failures before opening the circuit | `5` |
| `recoveryTimeoutSeconds` | Time to wait before attempting recovery | `60` |
| `halfOpenMaxCalls` | Maximum calls allowed in half-open state | `3` |

### State Store Interface

Implement `CircuitBreakerStoreInterface` for custom persistence:

```php
<?php

use NullOdyssey\CircuitBreaker\CircuitBreakerStoreInterface;
use NullOdyssey\CircuitBreaker\CircuitBreakerStateData;

class RedisStateStore implements CircuitBreakerStoreInterface
{
    public function __construct(private Redis $redis) {}

    public function load(string $serviceName): ?CircuitBreakerStateData
    {
        $data = $this->redis->get("circuit_breaker:$serviceName");
        return $data ? CircuitBreakerStateData::fromArray(json_decode($data, true)) : null;
    }

    public function save(string $serviceName, CircuitBreakerStateData $stateData): void
    {
        $this->redis->set("circuit_breaker:$serviceName", json_encode($stateData->toArray()));
    }

    public function lock(string $serviceName, callable $callback): mixed
    {
        $lockKey = "circuit_breaker_lock:$serviceName";
        
        if (!$this->redis->set($lockKey, '1', ['nx', 'ex' => 10])) {
            throw new \RuntimeException("Could not acquire lock for $serviceName");
        }

        try {
            $currentState = $this->load($serviceName);
            return $callback($currentState);
        } finally {
            $this->redis->del($lockKey);
        }
    }

    public function delete(string $serviceName): void
    {
        $this->redis->del("circuit_breaker:$serviceName");
    }

    public function exists(string $serviceName): bool
    {
        return $this->redis->exists("circuit_breaker:$serviceName");
    }

    public function clear(): void
    {
        $keys = $this->redis->keys('circuit_breaker:*');
        if ($keys) {
            $this->redis->del($keys);
        }
    }
}
```

## 🎯 Symfony Integration

The Circuit Breaker library integrates seamlessly with Symfony applications using the Lock component and Redis component for distributed state management.

### Installation

```bash
composer require nullodyssey/circuit-breaker
composer require symfony/lock
composer require symfony/redis-messenger
```

### Redis State Store

Create a Symfony-compatible Redis state store using Symfony's Lock component:

```php
<?php
// src/CircuitBreaker/SymfonyRedisStateStore.php

namespace App\CircuitBreaker;

use NullOdyssey\CircuitBreaker\CircuitBreakerStoreInterface;
use NullOdyssey\CircuitBreaker\CircuitBreakerStateData;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Lock\Store\RedisStore;
use Symfony\Contracts\Cache\CacheInterface;

class SymfonyRedisStateStore implements CircuitBreakerStoreInterface
{
    private const CACHE_PREFIX = 'circuit_breaker';
    private const LOCK_PREFIX = 'circuit_breaker_lock';
    private const LOCK_TTL = 30; // seconds

    public function __construct(
        private CacheInterface $cache,
        private LockFactory $lockFactory,
    ) {}

    public function load(string $serviceName): ?CircuitBreakerStateData
    {
        $cacheKey = self::CACHE_PREFIX . '.' . $serviceName;
        
        $data = $this->cache->get($cacheKey, function () {
            return null;
        });

        return $data ? CircuitBreakerStateData::fromArray($data) : null;
    }

    public function save(string $serviceName, CircuitBreakerStateData $stateData): void
    {
        $cacheKey = self::CACHE_PREFIX . '.' . $serviceName;
        
        // Cache for 1 hour (circuit breaker will refresh as needed)
        $this->cache->set($cacheKey, $stateData->toArray(), 3600);
    }

    public function lock(string $serviceName, callable $callback): mixed
    {
        $lockKey = self::LOCK_PREFIX . '.' . $serviceName;
        $lock = $this->lockFactory->createLock($lockKey, self::LOCK_TTL);

        if (!$lock->acquire()) {
            throw new \RuntimeException("Could not acquire lock for service: $serviceName");
        }

        try {
            $currentState = $this->load($serviceName);
            $result = $callback($currentState);

            // If callback returns state data, save it
            if ($result instanceof CircuitBreakerStateData) {
                $this->save($serviceName, $result);
            }

            return $result;
        } finally {
            $lock->release();
        }
    }

    public function delete(string $serviceName): void
    {
        $cacheKey = self::CACHE_PREFIX . '.' . $serviceName;
        $this->cache->delete($cacheKey);
    }

    public function exists(string $serviceName): bool
    {
        $cacheKey = self::CACHE_PREFIX . '.' . $serviceName;
        
        return $this->cache->get($cacheKey, function () {
            return false;
        }) !== false;
    }

    public function clear(): void
    {
        $this->cache->clear();
    }
}
```

### Service Configuration

Configure the circuit breaker services in your Symfony application:

```yaml
# config/services.yaml
services:
    # Circuit breaker state store
    App\CircuitBreaker\SymfonyRedisStateStore:
        arguments:
            - '@cache.app'
            - '@lock.factory'

    # Circuit breaker factory
    circuit_breaker.factory:
        class: NullOdyssey\CircuitBreaker\CircuitBreakerFactory
        arguments:
            $defaultFailureThreshold: 5
            $defaultRecoveryTimeoutSeconds: 60
            $defaultHalfOpenMaxCalls: 3
            $stateStore: '@App\CircuitBreaker\SymfonyRedisStateStore'

    # Alias for easier injection
    NullOdyssey\CircuitBreaker\CircuitBreakerFactory: '@circuit_breaker.factory'
```

### Usage in Controllers

Use the circuit breaker in your Symfony controllers:

```php
<?php
// src/Controller/ApiController.php

namespace App\Controller;

use NullOdyssey\CircuitBreaker\CircuitBreakerFactory;
use NullOdyssey\CircuitBreaker\CircuitBreakerException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ApiController extends AbstractController
{
    public function __construct(
        private CircuitBreakerFactory $circuitBreakerFactory,
        private HttpClientInterface $httpClient,
    ) {}

    #[Route('/api/external-data', name: 'external_data', methods: ['GET'])]
    public function getExternalData(): JsonResponse
    {
        $circuitBreaker = $this->circuitBreakerFactory->circuitFor('external-api');

        try {
            $data = $circuitBreaker->call(function () {
                // Call external API with timeout
                $response = $this->httpClient->request('GET', 'https://api.example.com/data', [
                    'timeout' => 5,
                    'headers' => [
                        'Accept' => 'application/json',
                    ],
                ]);

                if ($response->getStatusCode() !== 200) {
                    throw new \RuntimeException('API returned non-200 status');
                }

                return $response->toArray();
            });

            return new JsonResponse([
                'success' => true,
                'data' => $data,
                'circuit_state' => $circuitBreaker->getState()->value,
            ]);

        } catch (CircuitBreakerException $e) {
            // Circuit breaker is open - return cached data or error
            return new JsonResponse([
                'success' => false,
                'error' => 'Service temporarily unavailable',
                'circuit_state' => 'open',
                'retry_after' => $circuitBreaker->nextAttemptTime()?->format('c'),
            ], Response::HTTP_SERVICE_UNAVAILABLE);

        } catch (\Exception $e) {
            // Service call failed - circuit breaker recorded the failure
            return new JsonResponse([
                'success' => false,
                'error' => 'External service failed',
                'circuit_state' => $circuitBreaker->getState()->value,
            ], Response::HTTP_BAD_GATEWAY);
        }
    }

    #[Route('/api/circuit-breaker/status', name: 'circuit_status', methods: ['GET'])]
    public function getCircuitBreakerStatus(): JsonResponse
    {
        $services = ['external-api', 'payment-service', 'user-service'];
        $status = [];

        foreach ($services as $serviceName) {
            $circuitBreaker = $this->circuitBreakerFactory->circuitFor($serviceName);
            
            $status[$serviceName] = [
                'state' => $circuitBreaker->getState()->value,
                'failure_count' => $circuitBreaker->failureCount(),
                'half_open_calls' => $circuitBreaker->halfOpenCallCount(),
                'half_open_successes' => $circuitBreaker->halfOpenSuccessCount(),
                'last_failure' => $circuitBreaker->lastFailureTime()?->format('c'),
                'next_attempt' => $circuitBreaker->nextAttemptTime()?->format('c'),
            ];
        }

        return new JsonResponse($status);
    }

    #[Route('/api/circuit-breaker/reset/{serviceName}', name: 'circuit_reset', methods: ['POST'])]
    public function resetCircuitBreaker(string $serviceName): JsonResponse
    {
        try {
            $circuitBreaker = $this->circuitBreakerFactory->circuitFor($serviceName);
            $circuitBreaker->reset();

            return new JsonResponse([
                'success' => true,
                'message' => "Circuit breaker for {$serviceName} has been reset",
                'state' => $circuitBreaker->getState()->value,
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}
```

### Service Integration

Create a dedicated service for external API calls:

```php
<?php
// src/Service/ExternalApiService.php

namespace App\Service;

use NullOdyssey\CircuitBreaker\CircuitBreakerFactory;
use NullOdyssey\CircuitBreaker\CircuitBreakerException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class ExternalApiService
{
    private const SERVICE_NAME = 'external-api';

    public function __construct(
        private CircuitBreakerFactory $circuitBreakerFactory,
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
    ) {}

    public function fetchUserData(int $userId): array
    {
        $circuitBreaker = $this->circuitBreakerFactory->circuitFor(self::SERVICE_NAME);

        try {
            return $circuitBreaker->call(function () use ($userId) {
                $this->logger->info('Calling external API for user', ['user_id' => $userId]);

                $response = $this->httpClient->request('GET', "/api/users/{$userId}", [
                    'timeout' => 3,
                    'max_redirects' => 2,
                ]);

                if ($response->getStatusCode() !== 200) {
                    throw new \RuntimeException('API returned status: ' . $response->getStatusCode());
                }

                $data = $response->toArray();
                $this->logger->info('External API call successful', ['user_id' => $userId]);

                return $data;
            });

        } catch (CircuitBreakerException $e) {
            $this->logger->warning('Circuit breaker is open for external API', [
                'service' => self::SERVICE_NAME,
                'next_attempt' => $circuitBreaker->nextAttemptTime()?->format('c'),
            ]);

            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('External API call failed', [
                'service' => self::SERVICE_NAME,
                'error' => $e->getMessage(),
                'failure_count' => $circuitBreaker->failureCount(),
            ]);

            throw $e;
        }
    }
}
```

### Monitoring with Symfony Console

Create a console command to monitor circuit breaker status:

```php
<?php
// src/Command/CircuitBreakerStatusCommand.php

namespace App\Command;

use NullOdyssey\CircuitBreaker\CircuitBreakerFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'circuit-breaker:status',
    description: 'Display circuit breaker status for all services',
)]
class CircuitBreakerStatusCommand extends Command
{
    private const SERVICES = [
        'external-api',
        'payment-service',
        'user-service',
        'notification-service',
    ];

    public function __construct(
        private CircuitBreakerFactory $circuitBreakerFactory,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $rows = [];
        
        foreach (self::SERVICES as $serviceName) {
            $circuitBreaker = $this->circuitBreakerFactory->circuitFor($serviceName);
            
            $state = $circuitBreaker->getState()->value;
            $stateIcon = match($state) {
                'closed' => '🟢',
                'open' => '🔴',
                'half_open' => '🟡',
                default => '❓',
            };
            
            $rows[] = [
                $serviceName,
                $stateIcon . ' ' . strtoupper($state),
                $circuitBreaker->failureCount(),
                $circuitBreaker->halfOpenCallCount(),
                $circuitBreaker->lastFailureTime()?->format('Y-m-d H:i:s') ?? 'Never',
                $circuitBreaker->nextAttemptTime()?->format('Y-m-d H:i:s') ?? 'N/A',
            ];
        }

        $io->table(
            ['Service', 'State', 'Failures', 'Half-Open Calls', 'Last Failure', 'Next Attempt'],
            $rows
        );

        return Command::SUCCESS;
    }
}
```

This comprehensive Symfony integration provides:

- **Redis-based state storage** with Symfony's Lock component
- **Service container configuration** for dependency injection
- **Controller examples** showing real-world usage
- **Dedicated service classes** for clean architecture
- **Console command** for monitoring
- **Error handling** and logging integration
- **Thread-safe operations** across multiple Symfony processes

## 🎭 Circuit Breaker States

### 🟢 Closed State
- **Default state**: All calls pass through normally
- **Failure tracking**: Counts consecutive failures
- **Transition**: Opens when failure threshold is reached

### 🔴 Open State
- **Fail fast**: All calls immediately throw `CircuitBreakerException`
- **Recovery timer**: Waits for the configured timeout period
- **Transition**: Moves to Half-Open after timeout expires

### 🟡 Half-Open State
- **Testing phase**: Allows limited number of calls to test service recovery
- **Success tracking**: Counts successful calls in this state
- **Transitions**:
  - **To Closed**: When all test calls succeed
  - **To Open**: On any failure

## 🔄 State Transitions

```
    Closed ──[failures ≥ threshold]──> Open
       ↑                                ↓
       │                         [timeout expires]
       │                                ↓
       └──[success calls ≥ limit]── Half-Open
                                        ↑
                                        │
                                   [any failure]
                                        ↓
                                      Open
```

## 📊 Monitoring & Metrics

### Circuit Breaker Status

```php
<?php

// Check current state
if ($circuitBreaker->isOpen()) {
    echo "Circuit is open - failing fast";
} elseif ($circuitBreaker->isHalfOpen()) {
    echo "Circuit is half-open - testing recovery";
} else {
    echo "Circuit is closed - operating normally";
}

// Get detailed metrics
echo "Failure count: " . $circuitBreaker->failureCount() . "\n";
echo "Half-open calls: " . $circuitBreaker->halfOpenCallCount() . "\n";
echo "Half-open successes: " . $circuitBreaker->halfOpenSuccessCount() . "\n";

$lastFailure = $circuitBreaker->lastFailureTime();
if ($lastFailure) {
    echo "Last failure: " . $lastFailure->format('Y-m-d H:i:s') . "\n";
}

$nextAttempt = $circuitBreaker->nextAttemptTime();
if ($nextAttempt) {
    echo "Next attempt: " . $nextAttempt->format('Y-m-d H:i:s') . "\n";
}
```

### Factory Management

```php
<?php

// Get all managed circuit breakers
$allCircuits = $factory->circuitBreakers();

foreach ($allCircuits as $serviceName => $circuitBreaker) {
    echo "$serviceName: " . $circuitBreaker->getState()->value . "\n";
}

// Reset specific service
$factory->resetService('problematic-service');

// Reset all services
$factory->resetAll();
```

## 🏗️ Architecture

### Class Hierarchy

```
CircuitBreakerInterface
├── CircuitBreaker (basic implementation)
└── PersistentCircuitBreaker (with state store)

CircuitBreakerFactoryInterface
└── CircuitBreakerFactory

CircuitBreakerStoreInterface
└── InMemoryStore (basic implementation)

CircuitBreakerStateData (DTO for persistence)
CircuitBreakerState (enum: CLOSED, OPEN, HALF_OPEN)
CircuitBreakerException (thrown when circuit is open)
```

## 🔒 Thread Safety

The persistent circuit breaker provides thread safety through:

- **Locking mechanism**: Prevents concurrent state modifications
- **Atomic operations**: State changes are atomic within locks
- **Consistent state**: Ensures all workers see the same state

## 🗺️ Roadmap

The Circuit Breaker library is continuously evolving. Here's our roadmap for upcoming features and improvements:

| Feature                | Impact | Complexity | Priority |
|------------------------|--------|------------|----------|
| Metrics Collection     | High | Medium | High |
| Fallback Mechanisms    | High | Medium | High |
| Rolling Window         | Medium | Low | Medium |
| Database Store         | Medium | Medium | Medium |
| Frameworks Integration | High | Medium | Medium |
| Health Checks          | Medium | Low | Low |
| OpenTelemetry          | Low | High | Low |

## 🎯 Contributing to the Roadmap

We welcome contributions for any of these features! Here's how you can help:

### 🚀 **High Priority (Looking for Contributors)**
- **Metrics Collection Interface** - Add comprehensive metrics support
- **Fallback Mechanisms** - Implement fallback strategies
- **Configuration Validation** - Add runtime config validation

### 🔧 **Getting Started**
1. Check our [GitHub Issues](https://github.com/nullodyssey/circuit-breaker/issues) for feature requests
2. Join the discussion on feature design
3. Submit a pull request with your implementation
4. Help with testing and documentation

### 💡 **Feature Requests**
Have an idea for a new feature? Please:
1. Open a GitHub issue with the "enhancement" label
2. Provide detailed use cases and examples
3. Discuss the API design with the community
4. Consider contributing the implementation

## 🤝 Contributing

We welcome contributions! Please:

1. Fork the repository
2. Create a feature branch
3. Add tests for new functionality
4. Ensure all tests pass
5. Submit a pull request

### Development Setup

```bash
# Clone the repository
git clone git@github.com:nullodyssey/circuit-breaker.git
cd circuit-breaker

# Start development environment
make start

# Install dependencies
make composer c='install'

# Run tests
make test

# Run quality checks
make quality
```

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE.md) file for details.

## 📚 Further Reading

- [Circuit Breaker Pattern](https://martinfowler.com/bliki/CircuitBreaker.html) by Martin Fowler
- [Release It!](https://pragprog.com/titles/mnee2/release-it-second-edition/) by Michael Nygard
- [Microservices Patterns](https://microservices.io/patterns/reliability/circuit-breaker.html)

---

Made with ❤️ by [NullOdyssey](https://github.com/nullodyssey)