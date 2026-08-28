<?php

namespace Tests\Feature;

use App\Actions\Cases\AdvanceCaseStage;
use App\Enums\CaseStatus;
use App\Models\CaseEvent;
use App\Models\CaseRecord;
use App\Models\CaseStageStatus;
use App\Models\Network;
use App\Models\Organization;
use App\Models\Subject;
use App\Models\User;
use App\Models\WorkflowStage;
use App\Models\WorkflowTemplate;
use App\Support\Cases\CaseWorkspaceStages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CaseClosingFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_advancing_terminal_stage_closes_case_and_marks_stepper_done(): void
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

        $subject = Subject::create([
            'network_id' => $network->id,
            'organization_id' => $clinic->id,
            'label_name' => 'Paciente',
            'is_active' => true,
        ]);

        $user = User::create([
            'network_id' => $network->id,
            'name' => 'Operador',
            'email' => 'operador@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        $workflow = WorkflowTemplate::create([
            'network_id' => $network->id,
            'name' => 'Flujo test',
            'is_default' => true,
            'is_active' => true,
        ]);

        $stage = WorkflowStage::create([
            'workflow_template_id' => $workflow->id,
            'key' => 'consultation',
            'label' => 'Consulta',
            'sort_order' => 0,
            'is_terminal' => true,
        ]);

        $case = CaseRecord::create([
            'network_id' => $network->id,
            'organization_id' => $clinic->id,
            'workflow_template_id' => $workflow->id,
            'subject_id' => $subject->id,
            'current_stage_id' => $stage->id,
            'code' => 'CASE-TERMINAL',
            'title' => 'Caso terminal',
            'status' => CaseStatus::Open,
        ]);

        CaseStageStatus::create([
            'case_id' => $case->id,
            'workflow_stage_id' => $stage->id,
            'status' => 'in_progress',
        ]);

        $closedCase = app(AdvanceCaseStage::class)->handle($case, $user);

        $this->assertSame(CaseStatus::Closed, $closedCase->status);
        $this->assertNull($closedCase->current_stage_id);
        $this->assertSame($user->id, $closedCase->closed_by);
        $this->assertNotNull($closedCase->closed_at);
        $this->assertTrue(
            CaseEvent::query()
                ->where('case_id', $closedCase->id)
                ->where('type', 'case_closed')
                ->exists()
        );

        $state = CaseWorkspaceStages::stageState($closedCase->load('stageStatuses.stage'), $stage);
        $this->assertSame('done', $state);
    }
}
