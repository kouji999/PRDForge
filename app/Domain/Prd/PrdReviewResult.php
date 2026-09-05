<?php

namespace App\Domain\Prd;

class PrdReviewResult
{
    /**
     * @param  list<string>  $gaps
     * @param  list<string>  $contradictions
     * @param  list<string>  $suggestions
     */
    public function __construct(
        public readonly array $gaps,
        public readonly array $contradictions,
        public readonly array $suggestions,
        public readonly string $verdict,
    ) {}

    public function toArray(): array
    {
        return [
            'gaps' => $this->gaps,
            'contradictions' => $this->contradictions,
            'suggestions' => $this->suggestions,
            'verdict' => $this->verdict,
        ];
    }
}
