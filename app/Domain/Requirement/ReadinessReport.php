<?php

namespace App\Domain\Requirement;

class ReadinessReport
{
    /**
     * @param  list<array{key: string, label: string, status: string, note: ?string}>  $criteria
     * @param  list<string>  $missing
     */
    public function __construct(
        public readonly bool $ready,
        public readonly int $score,
        public readonly array $criteria,
        public readonly array $missing,
    ) {}

    public function toArray(): array
    {
        return [
            'ready' => $this->ready,
            'score' => $this->score,
            'criteria' => $this->criteria,
            'missing' => $this->missing,
        ];
    }
}
