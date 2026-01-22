<?php
declare(strict_types=1);

namespace Modules\Api\OpenApi\Attributes;

#[\Attribute(\Attribute::TARGET_METHOD)]
class ApiRoute
{
    public string $method;
    public string $path;
    public bool $auth;

    public function __construct(string $method, string $path, bool $auth = true)
    {
        $this->method = strtoupper($method);
        $this->path = $path;
        $this->auth = $auth;
    }
}
