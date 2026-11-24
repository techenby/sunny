<?php

namespace App\Http\Integrations\Sunny;

use Exception;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Response;
use Throwable;

class EmailVerificationRequired extends RequestException
{
    public function __construct(?Response $response = null, ?Throwable $previous = null)
    {
        if ($response !== null) {
            $this->response = $response;
        }

        Exception::__construct('Verify your email address to keep using SunnyHome.', 0, $previous);
    }
}
