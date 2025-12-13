<?php
namespace Core {

    class Lang
    {
        private string $default;
        private string $fallback;
        private string $appLangPath;
        private string $modulesPath;
        private array $available;
        private array $messages = [];
        private string $current = '';
        private array $namespaces = [];
        private array $namespacedMessages = [];

        public function __construct(array $config, string $appLangPath, string $modulesPath)
        {
            $this->default = $config['default'] ?? 'en';
            $this->fallback = $config['fallback'] ?? $this->default;
            $this->available = $config['available'] ?? [$this->default];
            $this->appLangPath = rtrim($appLangPath, '/');
            $this->modulesPath = rtrim($modulesPath, '/');
        }

        public function addNamespace(string $namespace, string $path): void
        {
            $this->namespaces[$namespace] = rtrim($path, '/');
        }

        public function setAvailable(array $available): void
        {
            $this->available = array_values(array_unique($available));
        }

        public function setDefault(string $locale): void
        {
            $this->default = $locale;
        }

        public function setFallback(string $locale): void
        {
            $this->fallback = $locale;
        }

        public function detect(Request $request): string
        {
            $param = $_GET['lang'] ?? null;
            if ($param && in_array($param, $this->available, true)) {
                return $param;
            }
            $cookie = $_COOKIE['locale'] ?? null;
            if ($cookie && in_array($cookie, $this->available, true)) {
                return $cookie;
            }
            $header = $request->headers['Accept-Language'] ?? '';
            if ($header) {
                $langs = explode(',', $header);
                foreach ($langs as $lang) {
                    $lang = strtolower(substr(trim($lang), 0, 2));
                    if (in_array($lang, $this->available, true)) {
                        return $lang;
                    }
                }
            }
            return $this->default;
        }

        public function load(string $locale): void
        {
            $this->current = in_array($locale, $this->available, true) ? $locale : $this->fallback;
            $this->namespacedMessages = [];
            $this->messages = $this->loadLangFiles($this->appLangPath, $this->current);
            foreach (glob($this->modulesPath . '/*/lang/' . $this->current . '.php') as $file) {
                $this->messages = array_merge($this->messages, include $file);
            }
            $this->loadNamespaces($this->current);
            if ($this->fallback !== $this->current) {
                $fallbackMessages = $this->loadLangFiles($this->appLangPath, $this->fallback);
                $this->messages = array_replace($fallbackMessages, $this->messages);
                foreach (glob($this->modulesPath . '/*/lang/' . $this->fallback . '.php') as $file) {
                    $this->messages = array_replace($this->messages, include $file);
                }
                $this->loadNamespaces($this->fallback, true);
            }
        }

        private function loadLangFiles(string $path, string $locale): array
        {
            $messages = [];
            $file = $path . '/' . $locale . '.php';
            if (file_exists($file)) {
                $messages = include $file;
            }
            return is_array($messages) ? $messages : [];
        }

        public function current(): string
        {
            return $this->current ?: $this->default;
        }

        public function get(string $key, array $params = []): string
        {
            $message = $key;
            if (str_contains($key, '::')) {
                [$ns, $inner] = explode('::', $key, 2);
                $nsMessages = $this->namespacedMessages[$ns] ?? [];
                $message = $nsMessages[$inner] ?? ($this->messages[$key] ?? $key);
            } else {
                $message = $this->messages[$key] ?? $key;
            }
            foreach ($params as $k => $v) {
                $message = str_replace('{' . $k . '}', (string)$v, $message);
            }
            return $message;
        }

        private function loadNamespaces(string $locale, bool $fallbackMode = false): void
        {
            foreach ($this->namespaces as $ns => $path) {
                $file = $path . '/' . $locale . '.php';
                if (!file_exists($file)) {
                    continue;
                }
                $messages = include $file;
                if (!is_array($messages)) {
                    continue;
                }
                if (!isset($this->namespacedMessages[$ns])) {
                    $this->namespacedMessages[$ns] = [];
                }
                if ($fallbackMode) {
                    $this->namespacedMessages[$ns] = array_replace($messages, $this->namespacedMessages[$ns]);
                } else {
                    $this->namespacedMessages[$ns] = array_merge($this->namespacedMessages[$ns], $messages);
                }
            }
        }
    }
}

// Глобальный помощник перевода.
namespace {
    if (!function_exists('__')) {
        function __(string $key, array $params = []): string
        {
            /** @var \Core\Lang|null $langInstance */
            global $langInstance;
            if ($langInstance instanceof \Core\Lang) {
                return $langInstance->get($key, $params);
            }
            return $key;
        }
    }
}
