<?php

declare(strict_types=1);

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\SunnyServer;
use Illuminate\Support\Arr;
use Laravel\Mcp\Server\Contracts\Transport;

class SunnyTestServer extends SunnyServer
{
    public function __construct(Transport $transport)
    {
        parent::__construct($transport);

        $this->tools = Arr::flatten($this->tools);
    }
}
