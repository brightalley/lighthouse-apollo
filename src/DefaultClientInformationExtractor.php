<?php

namespace BrightAlley\LighthouseApollo;

use BrightAlley\LighthouseApollo\Contracts\ClientInformationExtractor;
use Illuminate\Http\Request;

class DefaultClientInformationExtractor implements ClientInformationExtractor
{
    private Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function getClientAddress(): ?string
    {
        return $this->request->getClientIp();
    }

    public function getClientName(): ?string
    {
        return $this->request->headers->get(
            'x-apollo-client-name',
            $this->request->headers->get('apollographql-client-name'),
        );
    }

    public function getClientReferenceId(): ?string
    {
        return $this->request->headers->get('x-apollo-client-reference-id');
    }

    public function getClientVersion(): ?string
    {
        return $this->request->headers->get(
            'x-apollo-client-version',
            $this->request->headers->get('apollographql-client-version'),
        );
    }
}
