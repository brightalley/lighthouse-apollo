<?php

namespace BrightAlley\LighthouseApollo\Exceptions;

use Exception;

class RegisterSchemaRequestFailedException extends Exception
{
    /**
     * Constructor.
     * @param int $errorNumber
     * @param string $errorMessage
     */
    public function __construct(int $errorNumber, string $errorMessage)
    {
        parent::__construct('Request to register schema with Apollo failed: ' . $errorMessage, $errorNumber);
    }
}
