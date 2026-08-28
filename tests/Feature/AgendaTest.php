<?php

namespace Tests\Feature;

use App\Enums\AgendaStatus;
use App\Enums\CaseStatus;
use App\Models\Agenda;
use App\Models\CaseRecord;
use App\Models\Network;
use App\Models\Organization;
use App\Models\Subject;
use App\Models\WorkflowTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgendaTest extends TestCase
{
    use RefreshDatabase;

    public function test_agenda_groups_cases_ordered_by_scheduled_time(): void
    {
        $network = Network::create([
            'name' => 'Red Test',
            'slug' => 'red-test',
            'industry_template_key' => 'generic',
            'is_active' => true,
        ]);

        $clinic = Organization::create([
            'network_id' => $network->id,
            'name' => 'Clínica Test',
            'slug' => 'clinica-test',
            'is_active' => true,
        ]);

        $subjectA = Subject::create([
            'network_id' => $network->id,
            'organization_id' => $clinic->id,
            'label_name' => 'Paciente A',
            'is_active' => true,
        ]);

        $subjectB = Subject::create([
            'network_id' => $network->id,
            'organization_id' => $clinic->id,
            'label_name' => 'Paciente B',
            'is_active' => true,
        ]);

        $visitDate = now()->addDay()->startOfDay();

        $workflow = WorkflowTemplate::create([
            'network_id' => $network->id,
            'name' => 'Flujo test',
            'is_default' => true,
            'is_active' => true,
        ]);

        $agenda = Agenda::create([
            'network_id' => $network->id,
            'organization_id' => $clinic->id,
            'scheduled_date' => $visitDate,
            'start_time' => '10:00:00',
            'status' => AgendaStatus::Planned,
        ]);

        CaseRecord::create([
            'network_id' => $network->id,
            'organization_id' => $clinic->id,
            'workflow_template_id' => $workflow->id,
            'subject_id' => $subjectB->id,
            'agenda_id' => $agenda->id,
            'scheduled_at' => $visitDate->copy()->setTime(11, 0),
            'code' => 'CASE-B',
            'title' => 'Paciente tarde',
            'status' => CaseStatus::Open,
        ]);

        CaseRecord::create([
            'network_id' => $network->id,
            'organization_id' => $clinic->id,
            'workflow_template_id' => $workflow->id,
            'subject_id' => $subjectA->id,
            'agenda_id' => $agenda->id,
            'scheduled_at' => $visitDate->copy()->setTime(10, 0),
            'code' => 'CASE-A',
            'title' => 'Paciente temprano',
            'status' => CaseStatus::Open,
        ]);

        $cases = $agenda->fresh()->cases;

        $this->assertCount(2, $cases);
        $this->assertEquals('CASE-A', $cases->first()->code);
        $this->assertEquals('CASE-B', $cases->last()->code);
    }
}
