<?php

namespace App\Actions\Cases;

use App\Models\CaseRecord;
use Illuminate\Support\Facades\DB;

class AssignAgendaOrder
{
    public function handle(CaseRecord $case): CaseRecord
    {
        if ($case->agenda_id === null) {
            if ($case->agenda_order !== null) {
                $case->update(['agenda_order' => null]);
            }

            return $case->fresh() ?? $case;
        }

        if ($case->agenda_order !== null) {
            return $case;
        }

        return DB::transaction(function () use ($case): CaseRecord {
            $locked = CaseRecord::query()
                ->whereKey($case->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->agenda_order !== null) {
                return $locked;
            }

            $next = (int) CaseRecord::query()
                ->where('agenda_id', $locked->agenda_id)
                ->lockForUpdate()
                ->max('agenda_order');

            $locked->update(['agenda_order' => $next + 1]);

            return $locked->fresh() ?? $locked;
        });
    }
}
