<?php

namespace App\Observers;

use App\Actions\Cases\AssignAgendaOrder;
use App\Models\CaseRecord;

class CaseRecordObserver
{
    public function saving(CaseRecord $case): void
    {
        if ($case->isDirty('agenda_id')) {
            $case->agenda_order = null;
        }
    }

    public function saved(CaseRecord $case): void
    {
        if ($case->agenda_id && $case->agenda_order === null) {
            app(AssignAgendaOrder::class)->handle($case);
        }
    }
}
