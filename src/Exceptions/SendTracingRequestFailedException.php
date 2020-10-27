<?php

namespace BrightAlley\LighthouseApollo\Exceptions;

use Exception;

class SendTracingRequestFailedException extends Exception
{
    /**
     * Constructor.
     * @param int $errorNumber
     * @param string $errorMessage
     */
    public function __construct(int $errorNumber, string $errorMessage)
    {
        parent::__construct('Request to send tracing to Apollo failed: ' . $errorMessage, $errorNumber);
    }
}
