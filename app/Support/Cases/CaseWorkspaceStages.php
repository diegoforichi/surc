<?php

namespace App\Support\Cases;

use App\Enums\CaseStatus;
use App\Models\CaseRecord;
use App\Models\WorkflowStage;
use Illuminate\Support\Collection;

class CaseWorkspaceStages
{
    /** @var array<string, string> */
    protected const SECTION_STAGE_KEYS = [
        'payments' => 'pre_intent',
        'checklist' => 'confirmation',
        'summary' => 'summary',
        'consultation' => 'consultation',
    ];

    /** @var array<string, int> */
    protected const SECTION_SORT_FALLBACK = [
        'payments' => 0,
        'checklist' => 1,
        'summary' => 2,
        'consultation' => 3,
    ];

    /**
     * @return Collection<int, WorkflowStage>
     */
    public static function orderedStages(CaseRecord $case): Collection
    {
        return WorkflowStage::query()
            ->where('workflow_template_id', $case->workflow_template_id)
            ->orderBy('sort_order')
            ->get();
    }

    public static function stageState(CaseRecord $case, WorkflowStage $stage): string
    {
        if ($case->status === CaseStatus::Closed || $case->status === CaseStatus::Cancelled) {
            return 'done';
        }

        $status = $case->stageStatuses
            ->firstWhere('workflow_stage_id', $stage->id)
            ?->status;

        if ($status === 'done') {
            return 'done';
        }

        if ($status === 'in_progress') {
            return 'current';
        }

        $current = $case->currentStage;

        if ($current === null) {
            return 'pending';
        }

        if ($stage->sort_order < $current->sort_order) {
            return 'done';
        }

        if ($stage->id === $current->id) {
            return 'current';
        }

        return 'locked';
    }

    public static function sectionStage(CaseRecord $case, string $section): ?WorkflowStage
    {
        $stages = static::orderedStages($case);
        $preferredKey = static::SECTION_STAGE_KEYS[$section] ?? null;

        if ($preferredKey !== null) {
            $match = $stages->firstWhere('key', $preferredKey);

            if ($match !== null) {
                return $match;
            }
        }

        $index = static::SECTION_SORT_FALLBACK[$section] ?? null;

        return $index !== null ? $stages->get($index) : null;
    }

    public static function sectionState(CaseRecord $case, string $section): string
    {
        $stage = static::sectionStage($case, $section);

        return $stage ? static::stageState($case, $stage) : 'locked';
    }

    public static function canEditSection(CaseRecord $case, string $section): bool
    {
        return static::sectionState($case, $section) === 'current';
    }

    /**
     * @return \Illuminate\Support\Collection<int, \App\Models\StageRequirement>
     */
    public static function sectionRequirements(CaseRecord $case, string $section): \Illuminate\Support\Collection
    {
        $stage = static::sectionStage($case, $section);

        return $stage?->requirements()->get() ?? collect();
    }

    public static function assertCanEditSection(CaseRecord $case, string $section): void
    {
        if (! static::canEditSection($case, $section)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'stage' => 'Esta acción no está disponible en la etapa actual.',
            ]);
        }
    }
}
