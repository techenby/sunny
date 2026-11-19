<?php

namespace App\Http\Integrations\Sunny;

use Exception;

class EmailVerificationRequired extends Exception
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('Verify your email address to keep using SunnyHome.', 0, $previous);
    }
}
