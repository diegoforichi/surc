<?php

namespace Tests\Feature;

use App\Filament\Resources\OrganizationResource;
use App\Filament\Resources\SubjectResource\RelationManagers\CasesRelationManager;
use App\Filament\Resources\SubjectResource\RelationManagers\HistoryEntriesRelationManager;
use App\Support\Auth\RoleLabels;
use App\Support\Labels\OperationalStatusLabels;
use App\Support\Cases\CaseStatusDisplay;
use App\Enums\CaseStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VisibleLabelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_roles_statuses_and_history_tabs_use_spanish_labels(): void
    {
        $this->assertSame('Admin de red', RoleLabels::label('network_admin'));
        $this->assertSame('Admin de sede', RoleLabels::label('organization_admin'));
        $this->assertSame('Pendiente', OperationalStatusLabels::payment('pending'));
        $this->assertSame('Confirmado', OperationalStatusLabels::payment('confirmed'));
        $this->assertSame('Seña', OperationalStatusLabels::paymentType('deposit'));
        $this->assertSame('Completada con errores', OperationalStatusLabels::import('completed_with_errors'));
        $this->assertSame('Carrusel', OperationalStatusLabels::publicContentType('carousel'));
        $this->assertSame('En curso', CaseStatusDisplay::label('open'));
        $this->assertSame('Cancelado', CaseStatusDisplay::label(CaseStatus::Cancelled));
        $this->assertSame('Registro', terminology('history_entry', 'Registro'));
        $this->assertSame(
            terminology('case', 'Casos').' asociados',
            CasesRelationManager::getTitle(new \App\Models\Subject, ''),
        );
        $this->assertSame(
            terminology('history', 'Historial'),
            HistoryEntriesRelationManager::getTitle(new \App\Models\Subject, ''),
        );
        $this->assertSame('Sede', OrganizationResource::getModelLabel());
        $this->assertStringNotContainsString('subject history', strtolower(terminology('history_entry', 'Registro')));
    }
}
