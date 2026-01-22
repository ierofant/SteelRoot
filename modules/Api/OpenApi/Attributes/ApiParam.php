<?php
declare(strict_types=1);

namespace Modules\Api\OpenApi\Attributes;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class ApiParam
{
    public string $name;
    public string $in;
    public bool $required;
    public array|string $schema;

    public function __construct(string $name, string $in, bool $required = false, array|string $schema = 'string')
    {
        $this->name = $name;
        $this->in = $in;
        $this->required = $required;
        $this->schema = $schema;
    }
}
