<?php

namespace Tests\Feature;

use App\Actions\Agendas\ConfirmAgenda;
use App\Actions\Agendas\RecalculateAgendaStatus;
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

class AgendaConfirmationModesTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirm_strict_blocks_when_cases_are_incomplete(): void
    {
        [$agenda] = $this->seedAgendaWithSingleCase([
            'agenda' => ['confirm_mode' => 'strict', 'case_ready_criteria' => 'payment_confirmed'],
        ]);

        $result = app(ConfirmAgenda::class)->handle($agenda);

        $this->assertTrue($result['blocked']);
        $this->assertFalse($result['confirmed']);
        $this->assertSame(AgendaStatus::Planned, $agenda->fresh()->status);
    }

    public function test_confirm_warn_confirms_with_pending_cases(): void
    {
        [$agenda] = $this->seedAgendaWithSingleCase([
            'agenda' => ['confirm_mode' => 'warn', 'case_ready_criteria' => 'payment_confirmed'],
        ]);

        $result = app(ConfirmAgenda::class)->handle($agenda);

        $this->assertFalse($result['blocked']);
        $this->assertTrue($result['confirmed']);
        $this->assertNotEmpty($result['pending_titles']);
        $this->assertSame(AgendaStatus::Confirmed, $agenda->fresh()->status);
    }

    public function test_confirm_free_confirms_without_validation(): void
    {
        [$agenda] = $this->seedAgendaWithSingleCase([
            'agenda' => ['confirm_mode' => 'free', 'case_ready_criteria' => 'payment_confirmed'],
        ]);

        $result = app(ConfirmAgenda::class)->handle($agenda);

        $this->assertFalse($result['blocked']);
        $this->assertTrue($result['confirmed']);
        $this->assertSame(AgendaStatus::Confirmed, $agenda->fresh()->status);
    }

    public function test_recalculate_marks_done_when_all_cases_are_closed(): void
    {
        [$agenda, $case] = $this->seedAgendaWithSingleCase([
            'agenda' => ['auto_done' => true],
        ]);

        $case->update(['status' => CaseStatus::Closed]);
        app(RecalculateAgendaStatus::class)->handle($agenda);

        $this->assertSame(AgendaStatus::Done, $agenda->fresh()->status);
    }

    public function test_recalculate_marks_done_when_cases_are_closed_or_cancelled(): void
    {
        [$agenda, $firstCase] = $this->seedAgendaWithSingleCase([
            'agenda' => ['auto_done' => true],
        ]);

        $secondCase = CaseRecord::create([
            'network_id' => $firstCase->network_id,
            'organization_id' => $firstCase->organization_id,
            'workflow_template_id' => $firstCase->workflow_template_id,
            'subject_id' => $firstCase->subject_id,
            'agenda_id' => $agenda->id,
            'code' => 'CASE-002',
            'title' => 'Caso cancelado',
            'status' => CaseStatus::Cancelled,
        ]);

        $firstCase->update(['status' => CaseStatus::Closed]);
        $secondCase->refresh();

        app(RecalculateAgendaStatus::class)->handle($agenda);

        $this->assertSame(AgendaStatus::Done, $agenda->fresh()->status);
    }

    /**
     * @return array{0: Agenda, 1: CaseRecord}
     */
    protected function seedAgendaWithSingleCase(array $settings): array
    {
        $network = Network::create([
            'name' => 'Red Test',
            'slug' => 'red-test',
            'industry_template_key' => 'generic',
            'settings' => $settings,
            'is_active' => true,
        ]);

        $clinic = Organization::create([
            'network_id' => $network->id,
            'name' => 'Clínica Test',
            'slug' => 'clinica-test',
            'is_active' => true,
        ]);

        $subject = Subject::create([
            'network_id' => $network->id,
            'organization_id' => $clinic->id,
            'label_name' => 'Paciente',
            'is_active' => true,
        ]);

        $workflow = WorkflowTemplate::create([
            'network_id' => $network->id,
            'name' => 'Flujo test',
            'is_default' => true,
            'is_active' => true,
        ]);

        $agenda = Agenda::create([
            'network_id' => $network->id,
            'organization_id' => $clinic->id,
            'scheduled_date' => now()->addDay(),
            'status' => AgendaStatus::Planned,
        ]);

        $case = CaseRecord::create([
            'network_id' => $network->id,
            'organization_id' => $clinic->id,
            'workflow_template_id' => $workflow->id,
            'subject_id' => $subject->id,
            'agenda_id' => $agenda->id,
            'code' => 'CASE-001',
            'title' => 'Caso de prueba',
            'status' => CaseStatus::Open,
        ]);

        return [$agenda, $case];
    }
}
