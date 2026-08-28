<?php

namespace App\Models;

use App\Enums\CaseStatus;
use App\Support\Tenancy\BelongsToNetwork;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class CaseRecord extends Model implements HasMedia
{
    use BelongsToNetwork;
    use InteractsWithMedia;
    use LogsActivity;

    protected $table = 'cases';

    protected $fillable = [
        'network_id',
        'organization_id',
        'workflow_template_id',
        'subject_id',
        'current_stage_id',
        'agenda_id',
        'agenda_order',
        'scheduled_at',
        'code',
        'title',
        'status',
        'summary',
        'metadata',
        'opened_at',
        'closed_at',
        'closed_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => CaseStatus::class,
            'metadata' => 'array',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'scheduled_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->useLogName('case');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachments')
            ->useDisk('local');

        $this->addMediaCollection('signed_constancy')
            ->useDisk('local')
            ->singleFile();
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function workflowTemplate(): BelongsTo
    {
        return $this->belongsTo(WorkflowTemplate::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function currentStage(): BelongsTo
    {
        return $this->belongsTo(WorkflowStage::class, 'current_stage_id');
    }

    public function agenda(): BelongsTo
    {
        return $this->belongsTo(Agenda::class);
    }

    public function closedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function caseParties(): HasMany
    {
        return $this->hasMany(CaseParty::class, 'case_id');
    }

    public function stageStatuses(): HasMany
    {
        return $this->hasMany(CaseStageStatus::class, 'case_id');
    }

    public function requirementCompletions(): HasMany
    {
        return $this->hasMany(CaseRequirementCompletion::class, 'case_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(CaseEvent::class, 'case_id')->orderByDesc('created_at');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'case_id');
    }

    public function incorporatedHistoryEntry(): HasOne
    {
        return $this->hasOne(SubjectHistoryEntry::class, 'source_case_id');
    }
}
