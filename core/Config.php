<?php
namespace Core;

use ArrayAccess;

/**
 * Lightweight config repository that supports runtime merging.
 */
class Config implements ArrayAccess
{
    private array $items = [];

    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    public function all(): array
    {
        return $this->items;
    }

    public function get(string $key, $default = null)
    {
        return $this->items[$key] ?? $default;
    }

    public function set(string $key, $value): void
    {
        $this->items[$key] = $value;
    }

    public function merge(array $items): void
    {
        $this->items = array_replace_recursive($this->items, $items);
    }

    public function offsetExists($offset): bool
    {
        return array_key_exists($offset, $this->items);
    }

    public function offsetGet($offset)
    {
        return $this->items[$offset] ?? null;
    }

    public function offsetSet($offset, $value): void
    {
        $this->items[$offset] = $value;
    }

    public function offsetUnset($offset): void
    {
        unset($this->items[$offset]);
    }
}
