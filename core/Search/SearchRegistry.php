<?php
namespace Core\Search;

use Core\Logger;

class SearchRegistry
{
    private array $providers = [];

    public function register(SearchProviderInterface $provider): void
    {
        $this->providers[$provider->getKey()] = $provider;
    }

    /**
     * @return SearchProviderInterface[]
     */
    public function getProviders(): array
    {
        return $this->providers;
    }

    public function get(string $key): ?SearchProviderInterface
    {
        return $this->providers[$key] ?? null;
    }

    public function searchAll(string $query, array $selectedProviders = []): array
    {
        $results = [];
        $targets = $selectedProviders ?: array_keys($this->providers);
        foreach ($targets as $key) {
            $provider = $this->get($key);
            if (!$provider) {
                continue;
            }
            try {
                $results[$key] = $provider->search($query);
            } catch (\Throwable $e) {
                Logger::log('Search provider error [' . $key . ']: ' . $e->getMessage());
            }
        }
        return $results;
    }
}
