<?php
namespace Core;

class Events
{
    private array $listeners = [];

    public function listen(string $event, callable $listener): void
    {
        if (!isset($this->listeners[$event])) {
            $this->listeners[$event] = [];
        }
        $this->listeners[$event][] = $listener;
    }

    public function dispatch(string $event, array $payload = []): void
    {
        if (empty($this->listeners[$event])) {
            return;
        }
        foreach ($this->listeners[$event] as $listener) {
            try {
                $listener($payload);
            } catch (\Throwable $e) {
                Logger::log("Event listener failed for {$event}: " . $e->getMessage());
            }
        }
    }
}
