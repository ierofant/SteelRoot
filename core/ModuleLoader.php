<?php
namespace Core;

class ModuleLoader
{
    private string $modulesPath;
    private array $modules = [];

    public function __construct(string $modulesPath)
    {
        $this->modulesPath = rtrim($modulesPath, '/');
    }

    public function load(): array
    {
        foreach (glob($this->modulesPath . '/*/Module.php') as $moduleFile) {
            $moduleDir = dirname($moduleFile);
            $moduleName = basename($moduleDir);
            require_once $moduleFile;
            $class = 'Modules\\' . ucfirst($moduleName) . '\\Module';
            if (class_exists($class)) {
                $this->modules[$moduleName] = new $class($moduleDir);
            }
        }
        return $this->modules;
    }

    public function getModules(): array
    {
        return $this->modules;
    }
}
