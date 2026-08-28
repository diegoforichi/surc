<?php

namespace App\Http\Controllers;

use App\Models\CaseEvent;
use App\Models\CaseRecord;
use App\Models\CustomFieldDefinition;
use App\Support\Cases\CaseOperationalAccess;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class TicketController extends Controller
{
    public function show(CaseRecord $case): Response
    {
        $this->authorizeCase($case);
        $this->prepareCase($case);
        $this->recordPrint($case, 'html');

        return response()->view('ticket.show', compact('case'));
    }

    public function pdf(CaseRecord $case): Response
    {
        $this->authorizeCase($case);
        $this->prepareCase($case);
        $this->recordPrint($case, 'pdf');

        $pdf = Pdf::loadView('ticket.show', compact('case'))
            ->setPaper([0, 0, 226.77, 800], 'portrait');

        return $pdf->stream('constancia-'.$case->id.'.pdf');
    }

    public function report(CaseRecord $case): Response
    {
        $this->authorizeCase($case);

        $case->load([
            'organization',
            'subject.owner',
            'currentStage',
            'agenda.specialist',
            'workflowTemplate',
            'payments',
            'events.author',
            'events.technicalResponsible',
            'media',
        ]);

        $fieldDefinitions = CustomFieldDefinition::query()
            ->where('network_id', $case->network_id)
            ->where('entity_type', 'case')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $pdf = Pdf::loadView('ticket.report', [
            'case' => $case,
            'fieldDefinitions' => $fieldDefinitions,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream("informe-caso-{$case->id}.pdf");
    }

    protected function authorizeCase(CaseRecord $case): void
    {
        abort_unless(CaseOperationalAccess::canAccessCase($case), 403, 'No tiene permiso para acceder a este caso.');
    }

    protected function prepareCase(CaseRecord $case): void
    {
        $case->load([
            'organization.network',
            'subject.owner',
            'currentStage',
            'agenda.organization',
            'agenda.specialist.actorType',
            'workflowTemplate',
        ]);
    }

    protected function recordPrint(CaseRecord $case, string $channel): void
    {
        CaseEvent::create([
            'case_id' => $case->id,
            'type' => 'constancy_printed',
            'description' => $channel === 'pdf'
                ? 'Constancia descargada en PDF'
                : 'Constancia emitida para impresión',
            'created_by' => Auth::id(),
            'created_at' => now(),
        ]);
    }
}
