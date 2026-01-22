<?php
declare(strict_types=1);

namespace Modules\Api\OpenApi\Attributes;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class ApiSecurity
{
    public array $scopes;

    public function __construct(array $scopes = [])
    {
        $this->scopes = $scopes;
    }
}
