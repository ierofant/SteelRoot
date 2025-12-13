<?php
namespace Core\Search;

interface SearchProviderInterface
{
    public function getKey(): string;
    public function getLabel(): string;
    public function search(string $query, array $options = []): array;
    public function getOptions(): array;
}
