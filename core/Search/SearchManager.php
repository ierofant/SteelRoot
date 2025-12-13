<?php
namespace Core\Search;

class SearchManager
{
    private SearchRegistry $registry;

    public function __construct(SearchRegistry $registry)
    {
        $this->registry = $registry;
    }

    public function search(string $providerKey, string $query, array $options = []): array
    {
        $provider = $this->registry->get($providerKey);
        if (!$provider) {
            return [];
        }
        return $provider->search($query, $options);
    }
}
