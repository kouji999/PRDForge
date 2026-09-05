<?php

namespace App\Domain\Prd;

class SectionActionProposal
{
    public function __construct(
        public readonly int $sectionId,
        public readonly string $action,
        public readonly string $content,
        public readonly string $changelog,
        public readonly ?string $original,
    ) {}

    public function toArray(): array
    {
        return [
            'section_id' => $this->sectionId,
            'action' => $this->action,
            'content' => $this->content,
            'changelog' => $this->changelog,
            'original' => $this->original,
        ];
    }
}
