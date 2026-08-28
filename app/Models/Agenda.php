<?php

namespace App\Models;

use App\Enums\AgendaStatus;
use App\Support\Settings\NetworkSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use App\Support\Tenancy\BelongsToNetwork;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Agenda extends Model
{
    use BelongsToNetwork;

    protected $fillable = [
        'network_id',
        'organization_id',
        'specialist_party_id',
        'title',
        'scheduled_date',
        'start_time',
        'slot_minutes',
        'status',
        'notes',
        'instructions',
        'consent_text',
        'is_shared',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
            'slot_minutes' => 'integer',
            'status' => AgendaStatus::class,
            'is_shared' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function specialist(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'specialist_party_id');
    }

    public function cases(): HasMany
    {
        return $this->hasMany(CaseRecord::class, 'agenda_id')->orderBy('scheduled_at');
    }

    public function casesTotalCount(): int
    {
        return $this->cases()->count();
    }

    public function casesReadyCount(): int
    {
        return $this->readyCasesQuery()->count();
    }

    /**
     * @return Collection<int, CaseRecord>
     */
    public function pendingCasesForConfirmation(): Collection
    {
        $readyCaseIds = $this->readyCasesQuery()->pluck('id');

        return $this->cases()
            ->whereNotIn('id', $readyCaseIds)
            ->get();
    }

    public function suggestedScheduledAtForNextCase(): ?Carbon
    {
        if ($this->scheduled_date === null) {
            return null;
        }

        $baseDate = $this->scheduled_date->copy();

        if ($this->start_time !== null) {
            [$hour, $minute] = array_pad(explode(':', $this->start_time), 2, 0);
            $baseDate->setTime((int) $hour, (int) $minute);
        } else {
            $baseDate->startOfDay();
        }

        $slotMinutes = max(5, (int) ($this->slot_minutes ?? 30));
        $assignedCases = $this->cases()->count();

        return $baseDate->addMinutes($assignedCases * $slotMinutes);
    }

    public function optionLabel(): string
    {
        $date = $this->scheduled_date?->format('d/m/Y') ?? '—';
        $specialist = $this->specialist?->display_name ?? 'Sin especialista';
        $organization = $this->organization?->name ?? '—';
        $baseLabel = sprintf('%s — %s (%s)', $date, $specialist, $organization);

        if (filled($this->title)) {
            $baseLabel = $this->title.' · '.$baseLabel;
        }

        if ($this->is_shared) {
            return $baseLabel.' · abierta a la red';
        }

        return $baseLabel;
    }

    protected function readyCasesQuery(): Builder
    {
        $criteria = NetworkSettings::getForNetworkId(
            $this->network_id,
            'agenda.case_ready_criteria',
            'confirmation_stage',
        );

        $query = CaseRecord::query()
            ->where('agenda_id', $this->getKey());

        if ($criteria === 'payment_confirmed') {
            return $query->whereHas('payments', fn (Builder $paymentQuery) => $paymentQuery->where('status', 'confirmed'));
        }

        return $query->whereHas('stageStatuses', function (Builder $stageStatusQuery): void {
            $stageStatusQuery
                ->where('status', 'done')
                ->whereHas('stage', fn (Builder $stageQuery) => $stageQuery->where('key', 'confirmation'));
        });
    }
}
