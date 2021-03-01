<?php

namespace BrightAlley\LighthouseApollo\Listeners;

use BrightAlley\LighthouseApollo\QueryRequestStack;

class EndExecutionListener
{
    private QueryRequestStack $requestStack;

    public function __construct(QueryRequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    public function handle(): void
    {
        $this->requestStack->pop();
    }
}
