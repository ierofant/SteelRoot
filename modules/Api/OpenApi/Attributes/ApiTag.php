<?php
declare(strict_types=1);

namespace Modules\Api\OpenApi\Attributes;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class ApiTag
{
    public string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }
}
