<?php

namespace BrightAlley\LighthouseApollo;

use Nuwave\Lighthouse\Events\StartExecution;

class QueryRequestStack
{
    /**
     * @var StartExecution[]
     */
    private array $stack = [];

    public function push(StartExecution $execution): void
    {
        $this->stack[] = $execution;
    }

    public function pop(): ?StartExecution
    {
        return array_pop($this->stack);
    }

    public function current(): ?StartExecution
    {
        return end($this->stack);
    }
}
