<?php

namespace BrightAlley\LighthouseApollo\Listeners;

use BrightAlley\LighthouseApollo\QueryRequestStack;
use Nuwave\Lighthouse\Events\StartExecution;

class StartExecutionListener
{
    private QueryRequestStack $requestStack;

    public function __construct(QueryRequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    public function handle(StartExecution $request): void
    {
        $this->requestStack->push($request);
    }
}
