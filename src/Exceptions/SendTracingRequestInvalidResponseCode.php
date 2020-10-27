<?php

namespace BrightAlley\LighthouseApollo\Exceptions;

use Exception;

class SendTracingRequestInvalidResponseCode extends Exception
{
    /**
     * Constructor.
     *
     * @param int $responseCode
     * @param bool|string $responseText
     */
    public function __construct(int $responseCode, $responseText)
    {
        parent::__construct(
            'Unexpected response code ' . $responseCode . ' when sending tracing to Apollo Studio: ' . $responseText,
            $responseCode
        );
    }
}
