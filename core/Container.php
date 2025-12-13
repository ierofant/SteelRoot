<?php
namespace Core;

use RuntimeException;

class Container
{
    private array $definitions = [];
    private array $singletons = [];

    public function set(string $id, callable $factory): void
    {
        $this->definitions[$id] = $factory;
    }

    public function singleton(string $id, callable $factory): void
    {
        $this->definitions[$id] = $factory;
        $this->singletons[$id] = null;
    }

    public function get(string $id)
    {
        if (array_key_exists($id, $this->singletons)) {
            if ($this->singletons[$id] === null) {
                $this->singletons[$id] = $this->create($id);
            }
            return $this->singletons[$id];
        }
        return $this->create($id);
    }

    private function create(string $id)
    {
        if (!isset($this->definitions[$id])) {
            throw new RuntimeException("Service {$id} not defined");
        }
        return ($this->definitions[$id])($this);
    }
}
