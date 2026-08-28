<?php

namespace Tests\Feature;

use App\Enums\CaseStatus;
use App\Enums\RequirementType;
use App\Livewire\CaseWorkspace;
use App\Models\CaseRecord;
use App\Models\CaseRequirementCompletion;
use App\Models\Network;
use App\Models\Organization;
use App\Models\StageRequirement;
use App\Models\Subject;
use App\Models\User;
use App\Models\WorkflowStage;
use App\Models\WorkflowTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CaseAttachmentsWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_upload_and_delete_attachments_while_case_is_open(): void
    {
        Storage::fake('local');

        Permission::firstOrCreate(['name' => 'cases.operate']);

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
            'email' => 'operador-attachments@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $user->givePermissionTo('cases.operate');

        $workflow = WorkflowTemplate::create([
            'network_id' => $network->id,
            'name' => 'Flujo test',
            'is_default' => true,
            'is_active' => true,
        ]);

        $confirmation = WorkflowStage::create([
            'workflow_template_id' => $workflow->id,
            'key' => 'confirmation',
            'label' => 'Confirmación',
            'sort_order' => 0,
            'is_terminal' => false,
        ]);

        $requirement = StageRequirement::create([
            'workflow_stage_id' => $confirmation->id,
            'key' => 'prior_studies',
            'label' => 'Estudios previos adjuntos',
            'type' => RequirementType::File,
            'is_mandatory' => true,
            'sort_order' => 0,
        ]);

        $case = CaseRecord::create([
            'network_id' => $network->id,
            'organization_id' => $clinic->id,
            'workflow_template_id' => $workflow->id,
            'subject_id' => $subject->id,
            'current_stage_id' => $confirmation->id,
            'code' => 'CASE-ATTACH',
            'title' => 'Caso con adjuntos',
            'status' => CaseStatus::Open,
        ]);

        $this->actingAs($user);

        Livewire::test(CaseWorkspace::class, ['case' => $case])
            ->set('attachment', UploadedFile::fake()->create('estudio.pdf', 100))
            ->call('uploadAttachment');

        $case = $case->fresh();
        $this->assertCount(1, $case->getMedia('attachments'));

        $completion = CaseRequirementCompletion::query()
            ->where('case_id', $case->id)
            ->where('stage_requirement_id', $requirement->id)
            ->first();
        $this->assertNotNull($completion);
        $this->assertTrue($completion->is_completed);

        $mediaId = $case->getMedia('attachments')->first()->id;

        Livewire::test(CaseWorkspace::class, ['case' => $case])
            ->call('deleteAttachment', $mediaId);

        $case = $case->fresh();
        $this->assertCount(0, $case->getMedia('attachments'));

        $completion = CaseRequirementCompletion::query()
            ->where('case_id', $case->id)
            ->where('stage_requirement_id', $requirement->id)
            ->first();
        $this->assertNotNull($completion);
        $this->assertFalse($completion->is_completed);
    }
}
