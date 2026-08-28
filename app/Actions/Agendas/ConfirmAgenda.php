<?php

namespace App\Actions\Agendas;

use App\Enums\AgendaStatus;
use App\Models\Agenda;
use App\Support\Settings\NetworkSettings;

class ConfirmAgenda
{
    /**
     * @return array{blocked: bool, confirmed: bool, mode: string, pending_titles: array<int, string>}
     */
    public function handle(Agenda $agenda): array
    {
        $mode = NetworkSettings::getForNetworkId($agenda->network_id, 'agenda.confirm_mode', 'warn');
        $pending = $agenda->pendingCasesForConfirmation()->pluck('title')->all();

        if ($mode === 'strict' && $pending !== []) {
            return [
                'blocked' => true,
                'confirmed' => false,
                'mode' => $mode,
                'pending_titles' => $pending,
            ];
        }

        $agenda->update(['status' => AgendaStatus::Confirmed]);

        return [
            'blocked' => false,
            'confirmed' => true,
            'mode' => $mode,
            'pending_titles' => $pending,
        ];
    }
}
