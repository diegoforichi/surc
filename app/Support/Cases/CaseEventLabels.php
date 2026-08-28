<?php

namespace App\Support\Cases;

class CaseEventLabels
{
    public static function label(string $type): string
    {
        return match ($type) {
            'summary_updated' => 'Ficha actualizada',
            'consultation' => 'Consulta registrada',
            'stage_completed' => 'Etapa completada',
            'stage_started' => 'Etapa iniciada',
            'case_closed' => 'Caso atendido',
            'case_cancelled' => 'Caso cancelado',
            'constancy_printed' => 'Constancia emitida',
            'history_incorporated' => 'Registro incorporado al historial',
            default => 'Evento registrado',
        };
    }
}
