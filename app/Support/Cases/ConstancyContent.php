<?php

namespace App\Support\Cases;

use App\Models\CaseRecord;

class ConstancyContent
{
    public static function instructions(CaseRecord $case): string
    {
        $agenda = $case->agenda;

        if (filled($agenda?->instructions)) {
            return (string) $agenda->instructions;
        }

        return (string) ($case->workflowTemplate?->instructions ?? '');
    }

    public static function consent(CaseRecord $case): string
    {
        $agenda = $case->agenda;

        if (filled($agenda?->consent_text)) {
            return (string) $agenda->consent_text;
        }

        return (string) ($case->workflowTemplate?->consent_text ?? '');
    }

    public static function scheduledLabel(CaseRecord $case): string
    {
        if ($case->scheduled_at) {
            return $case->scheduled_at->format('d/m/Y H:i');
        }

        return 'Horario a confirmar';
    }
}
