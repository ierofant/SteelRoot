<?php
declare(strict_types=1);

namespace Modules\Api\OpenApi\Attributes;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class ApiResponse
{
    public int $code;
    public string $description;

    public function __construct(int $code, string $description)
    {
        $this->code = $code;
        $this->description = $description;
    }
}
