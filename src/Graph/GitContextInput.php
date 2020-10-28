<?php

namespace BrightAlley\LighthouseApollo\Graph;

use Illuminate\Contracts\Support\Arrayable;

class GitContextInput implements Arrayable
{
    use ArrayableTrait;

    private ?string $branch;
    private ?string $commit;
    private ?string $committer;
    private ?string $message;
    private ?string $remoteUrl;

    public function __construct(
        ?string $branch,
        ?string $commit,
        ?string $committer,
        ?string $message,
        ?string $remoteUrl
    ) {
        $this->branch = $branch;
        $this->commit = $commit;
        $this->committer = $committer;
        $this->message = $message;
        $this->remoteUrl = $remoteUrl;
    }
}
