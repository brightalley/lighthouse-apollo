<?php

namespace BrightAlley\LighthouseApollo\Contracts;

interface ClientInformationExtractor
{
    /**
     * Get the name of the client that made the current GraphQL request.
     *
     * @return string|null
     */
    public function getClientName(): ?string;

    /**
     * Get the version of the client that made the current GraphQL request.
     *
     * @return string|null
     */
    public function getClientVersion(): ?string;
}
