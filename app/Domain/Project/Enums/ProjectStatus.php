<?php

namespace App\Domain\Project\Enums;

enum ProjectStatus: string
{
    case DISCOVERY = 'discovery';
    case REQUIREMENTS_IN_PROGRESS = 'requirements_in_progress';
    case READY_FOR_PRD = 'ready_for_prd';
    case GENERATING = 'generating';
    case REVIEW = 'review';
    case APPROVED = 'approved';
    case ARCHIVED = 'archived';

    /** Valid transitions between statuses. Business rule — never trust frontend. */
    public function canTransitionTo(self $target): bool
    {
        $allowed = self::TRANSITIONS[$this->value] ?? [];

        return in_array($target->value, $allowed, true);
    }

    /** @return array<string, list<string>> */
    public static function transitionMap(): array
    {
        return self::TRANSITIONS;
    }

    /** @return array<string, string> */
    public static function transitionTargets(self $status): array
    {
        $allowed = self::TRANSITIONS[$status->value] ?? [];

        $targets = [];
        foreach ($allowed as $value) {
            $case = self::tryFrom($value);
            if ($case) {
                $targets[$value] = $case->label();
            }
        }

        return $targets;
    }

    public function label(): string
    {
        return match ($this) {
            self::DISCOVERY => 'Discovery',
            self::REQUIREMENTS_IN_PROGRESS => 'Requirements',
            self::READY_FOR_PRD => 'Ready for PRD',
            self::GENERATING => 'Generating',
            self::REVIEW => 'In Review',
            self::APPROVED => 'Approved',
            self::ARCHIVED => 'Archived',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::DISCOVERY => 'info',
            self::REQUIREMENTS_IN_PROGRESS => 'info',
            self::READY_FOR_PRD => 'ok',
            self::GENERATING => 'info',
            self::REVIEW => 'warn',
            self::APPROVED => 'ok',
            self::ARCHIVED => 'muted',
        };
    }

    /** Frontend badge map shared via Inertia. */
    public static function badgeMap(): array
    {
        return array_reduce(
            self::cases(),
            fn (array $carry, self $case) => $carry + [$case->value => ['label' => $case->label(), 'tone' => $case->tone()]],
            [],
        );
    }

    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        self::DISCOVERY->value => [
            self::REQUIREMENTS_IN_PROGRESS->value,
            self::ARCHIVED->value,
        ],
        self::REQUIREMENTS_IN_PROGRESS->value => [
            self::DISCOVERY->value,
            self::READY_FOR_PRD->value,
            self::ARCHIVED->value,
        ],
        self::READY_FOR_PRD->value => [
            self::REQUIREMENTS_IN_PROGRESS->value,
            self::GENERATING->value,
            self::ARCHIVED->value,
        ],
        self::GENERATING->value => [
            self::REVIEW->value,
            self::READY_FOR_PRD->value,
            self::ARCHIVED->value,
        ],
        self::REVIEW->value => [
            self::APPROVED->value,
            self::READY_FOR_PRD->value,
            self::REQUIREMENTS_IN_PROGRESS->value,
            self::ARCHIVED->value,
        ],
        self::APPROVED->value => [
            self::ARCHIVED->value,
        ],
        self::ARCHIVED->value => [],
    ];
}
