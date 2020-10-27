<?php

namespace BrightAlley\LighthouseApollo\Exceptions;

use Exception;

class InvalidTracingSendMode extends Exception
{
    public function __construct(string $tracingSendMode)
    {
        parent::__construct(
            'Invalid tracing send mode, check your lighthouse-apollo.send_tracing_mode setting: ' . $tracingSendMode
        );
    }
}
