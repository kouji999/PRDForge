<?php

namespace App\Domain\Requirement;

use App\Models\ProjectContext;
use App\Models\Requirement;

class ExtractionResult
{
    /**
     * @param  list<string>  $contextChanges
     * @param  list<Requirement>  $proposedRequirements
     */
    public function __construct(
        public readonly ?ProjectContext $context,
        public readonly array $contextChanges,
        public readonly array $proposedRequirements,
    ) {}
}
