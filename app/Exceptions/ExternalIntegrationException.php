<?php

namespace App\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

class ExternalIntegrationException extends RuntimeException implements ShouldntReport
{
    public function __construct(string $message, public readonly int $httpStatus = 503)
    {
        parent::__construct($message);
    }

    public function render($request)
    {
        return response()->json(['message' => $this->getMessage(), 'error' => $this->getMessage()], $this->httpStatus);
    }
}
