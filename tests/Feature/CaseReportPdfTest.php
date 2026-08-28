<?php

namespace Tests\Feature;

use App\Enums\CaseStatus;
use App\Models\CaseRecord;
use App\Models\Network;
use App\Models\Organization;
use App\Models\Subject;
use App\Models\User;
use App\Models\WorkflowTemplate;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CaseReportPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_operator_can_download_case_report_pdf(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $network = Network::create([
            'name' => 'Red Test',
            'slug' => 'red-test',
            'industry_template_key' => 'generic',
            'is_active' => true,
        ]);

        $organization = Organization::create([
            'network_id' => $network->id,
            'name' => 'Sede Test',
            'slug' => 'sede-test',
            'is_active' => true,
        ]);

        $subject = Subject::create([
            'network_id' => $network->id,
            'organization_id' => $organization->id,
            'label_name' => 'Paciente PDF',
            'is_active' => true,
        ]);

        $workflow = WorkflowTemplate::create([
            'network_id' => $network->id,
            'name' => 'Flujo test',
            'is_default' => true,
            'is_active' => true,
        ]);

        $case = CaseRecord::create([
            'network_id' => $network->id,
            'organization_id' => $organization->id,
            'workflow_template_id' => $workflow->id,
            'subject_id' => $subject->id,
            'code' => 'CASE-PDF',
            'title' => 'Caso PDF',
            'status' => CaseStatus::Open,
        ]);

        $user = User::create([
            'network_id' => $network->id,
            'organization_id' => $organization->id,
            'name' => 'Usuario PDF',
            'email' => 'usuario-pdf@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $user->assignRole('operator');

        $response = $this->actingAs($user)->get(route('cases.report', $case));

        $response->assertOk();
        $this->assertStringContainsString(
            'application/pdf',
            (string) $response->headers->get('content-type')
        );
    }
}
