<?php

namespace BrightAlley\LighthouseApollo\Graph;

use Illuminate\Contracts\Support\Arrayable;

class UploadSchemaVariables implements Arrayable
{
    use ArrayableTrait;

    private string $id;
    private string $schemaDocument;
    private string $tag;
    private ?GitContextInput $gitContext;

    public function __construct(
        string $id,
        string $schemaDocument,
        string $tag,
        ?GitContextInput $gitContext,
    ) {
        $this->id = $id;
        $this->tag = $tag;
        $this->gitContext = $gitContext;
        $this->schemaDocument = $schemaDocument;
    }
}
