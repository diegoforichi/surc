<?php

namespace App\Actions\Agendas;

use App\Enums\AgendaStatus;
use App\Enums\CaseStatus;
use App\Models\Agenda;
use App\Support\Settings\NetworkSettings;

class RecalculateAgendaStatus
{
    public function handle(Agenda $agenda): Agenda
    {
        if ($agenda->status === AgendaStatus::Cancelled) {
            return $agenda;
        }

        $autoDone = (bool) NetworkSettings::getForNetworkId($agenda->network_id, 'agenda.auto_done', true);

        if (! $autoDone) {
            return $agenda;
        }

        $totalCases = $agenda->cases()->count();
        $finishedCases = $agenda->cases()
            ->whereIn('status', [CaseStatus::Closed, CaseStatus::Cancelled])
            ->count();

        if ($totalCases > 0 && $totalCases === $finishedCases) {
            $agenda->update(['status' => AgendaStatus::Done]);
        }

        return $agenda->fresh();
    }
}
