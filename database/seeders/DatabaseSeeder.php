<?php

namespace Database\Seeders;

use App\Actions\Cases\InitializeCaseWorkflow;
use App\Actions\Templates\ApplyIndustryTemplate;
use App\Enums\AgendaStatus;
use App\Enums\CaseStatus;
use App\Models\ActorType;
use App\Models\Agenda;
use App\Models\CaseRecord;
use App\Models\Network;
use App\Models\Organization;
use App\Models\Party;
use App\Models\Subject;
use App\Models\User;
use App\Models\WorkflowTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);
        $this->call(HelpArticleSeeder::class);

        $platformOwner = new User;
        $platformOwner->forceFill([
            'name' => 'Dueño Plataforma',
            'email' => 'owner@surc.test',
            'password' => 'password',
            'is_platform_owner' => true,
            'is_active' => true,
        ])->save();
        $platformOwner->assignRole('platform_owner');

        $vetNetwork = Network::create([
            'name' => 'Red Veterinaria Demo',
            'slug' => 'red-veterinaria',
            'industry_template_key' => 'veterinary',
            'primary_color' => '#0d9488',
            'is_active' => true,
        ]);

        app(ApplyIndustryTemplate::class)->handle($vetNetwork, 'veterinary');

        $clinicA = Organization::create([
            'network_id' => $vetNetwork->id,
            'name' => 'Clínica Norte',
            'slug' => 'clinica-norte',
            'address' => 'Av. Principal 123',
            'phone' => '11-4444-1111',
            'is_active' => true,
            'show_in_directory' => true,
        ]);

        Organization::create([
            'network_id' => $vetNetwork->id,
            'name' => 'Clínica Sur',
            'slug' => 'clinica-sur',
            'address' => 'Calle Secundaria 456',
            'phone' => '11-4444-2222',
            'is_active' => true,
            'show_in_directory' => true,
        ]);

        $networkAdmin = User::create([
            'network_id' => $vetNetwork->id,
            'name' => 'Admin Red Veterinaria',
            'email' => 'admin@red-veterinaria.test',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $networkAdmin->assignRole('network_admin');

        $orgAdmin = User::create([
            'network_id' => $vetNetwork->id,
            'organization_id' => $clinicA->id,
            'name' => 'Admin Clínica Norte',
            'email' => 'admin@clinica-norte.test',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $orgAdmin->assignRole('organization_admin');

        $operator = User::create([
            'network_id' => $vetNetwork->id,
            'organization_id' => $clinicA->id,
            'name' => 'Operador Clínica',
            'email' => 'operador@clinica-norte.test',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $operator->assignRole('operator');

        $groomingNetwork = Network::create([
            'name' => 'Red Peluquería Demo',
            'slug' => 'red-peluqueria',
            'industry_template_key' => 'grooming',
            'primary_color' => '#db2777',
            'is_active' => true,
        ]);

        app(ApplyIndustryTemplate::class)->handle($groomingNetwork, 'grooming');

        Organization::create([
            'network_id' => $groomingNetwork->id,
            'name' => 'Peluquería Canina Centro',
            'slug' => 'peluqueria-centro',
            'address' => 'Plaza Central 10',
            'is_active' => true,
            'show_in_directory' => true,
        ]);

        $groomingAdmin = User::create([
            'network_id' => $groomingNetwork->id,
            'name' => 'Admin Red Peluquería',
            'email' => 'admin@red-peluqueria.test',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $groomingAdmin->assignRole('network_admin');

        $this->seedVetAgendaDemo($vetNetwork, $clinicA);
    }

    protected function seedVetAgendaDemo(Network $network, Organization $clinic): void
    {
        $clientType = ActorType::query()
            ->where('network_id', $network->id)
            ->where('key', 'client')
            ->first();

        $specialistType = ActorType::query()
            ->where('network_id', $network->id)
            ->where('key', 'specialist')
            ->first();

        $workflow = WorkflowTemplate::query()
            ->where('network_id', $network->id)
            ->where('is_default', true)
            ->first();

        if ($clientType === null || $specialistType === null || $workflow === null) {
            return;
        }

        $owner = Party::create([
            'network_id' => $network->id,
            'organization_id' => $clinic->id,
            'actor_type_id' => $clientType->id,
            'display_name' => 'María Demo',
            'phone' => '11-5555-0001',
            'is_active' => true,
        ]);

        $specialistUser = User::create([
            'network_id' => $network->id,
            'name' => 'Dr. Especialista Demo',
            'email' => 'especialista@clinica-norte.test',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $specialistUser->assignRole('specialist');

        $specialist = Party::create([
            'network_id' => $network->id,
            'organization_id' => $clinic->id,
            'actor_type_id' => $specialistType->id,
            'user_id' => $specialistUser->id,
            'display_name' => 'Dr. Especialista Demo',
            'is_active' => true,
        ]);

        $subjectA = Subject::create([
            'network_id' => $network->id,
            'organization_id' => $clinic->id,
            'owner_party_id' => $owner->id,
            'label_name' => 'Firulais',
            'code' => 'ANM-001',
            'metadata' => ['species' => 'Canino', 'breed' => 'Mestizo'],
            'is_active' => true,
        ]);

        $subjectB = Subject::create([
            'network_id' => $network->id,
            'organization_id' => $clinic->id,
            'owner_party_id' => $owner->id,
            'label_name' => 'Michi',
            'code' => 'ANM-002',
            'metadata' => ['species' => 'Felino', 'breed' => 'Siames'],
            'is_active' => true,
        ]);

        $visitDate = now()->addDay()->startOfDay();

        $agenda = Agenda::create([
            'network_id' => $network->id,
            'organization_id' => $clinic->id,
            'specialist_party_id' => $specialist->id,
            'title' => 'Visita cardiólogo',
            'scheduled_date' => $visitDate,
            'start_time' => '09:00:00',
            'status' => AgendaStatus::Confirmed,
            'notes' => 'Agenda demo con dos pacientes.',
        ]);

        $caseA = CaseRecord::create([
            'network_id' => $network->id,
            'organization_id' => $clinic->id,
            'workflow_template_id' => $workflow->id,
            'subject_id' => $subjectA->id,
            'agenda_id' => $agenda->id,
            'scheduled_at' => $visitDate->copy()->setTime(9, 0),
            'code' => 'DER-DEMO-001',
            'title' => 'Control cardiológico Firulais',
            'status' => CaseStatus::Open,
            'opened_at' => now(),
        ]);

        $caseB = CaseRecord::create([
            'network_id' => $network->id,
            'organization_id' => $clinic->id,
            'workflow_template_id' => $workflow->id,
            'subject_id' => $subjectB->id,
            'agenda_id' => $agenda->id,
            'scheduled_at' => $visitDate->copy()->setTime(9, 30),
            'code' => 'DER-DEMO-002',
            'title' => 'Control cardiológico Michi',
            'status' => CaseStatus::Open,
            'opened_at' => now(),
        ]);

        app(InitializeCaseWorkflow::class)->handle($caseA);
        app(InitializeCaseWorkflow::class)->handle($caseB);
    }
}
