<?php

namespace App\Filament\Pages;

use App\Support\Settings\NetworkSettings;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class OperationalPreferences extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 99;

    protected static ?string $title = 'Preferencias operativas';

    protected static ?string $navigationLabel = 'Preferencias operativas';

    protected static string $view = 'filament.pages.operational-preferences';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(NetworkSettings::all());
    }

    public static function canAccess(): bool
    {
        return Auth::user()?->can('config.manage') ?? false;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Confirmación de agenda')
                    ->schema([
                        Forms\Components\Select::make('agenda.confirm_mode')
                            ->label('Modo de confirmación')
                            ->options([
                                'strict' => 'Estricto (bloquear si hay casos incompletos)',
                                'warn' => 'Con alerta (permite confirmar con advertencias)',
                                'free' => 'Libre (sin validación)',
                            ])
                            ->required(),
                        Forms\Components\Select::make('agenda.case_ready_criteria')
                            ->label('Criterio de caso listo')
                            ->options([
                                'payment_confirmed' => 'Pago/seña confirmado',
                                'confirmation_stage' => 'Etapa de confirmación completada',
                            ])
                            ->required(),
                        Forms\Components\Toggle::make('agenda.auto_done')
                            ->label('Marcar agenda como realizada automáticamente')
                            ->helperText('Si está activo, la agenda pasa a Realizada cuando todos sus casos quedan cerrados.'),
                    ]),
                Forms\Components\Section::make('Finalización de consulta')
                    ->schema([
                        Forms\Components\Toggle::make('case.finish_requires_diagnosis')
                            ->label('Exigir diagnóstico para finalizar'),
                        Forms\Components\Toggle::make('case.finish_requires_technical')
                            ->label('Exigir técnico responsable para finalizar'),
                    ]),
                Forms\Components\Section::make('Entidades')
                    ->schema([
                        Forms\Components\Toggle::make('entities.subjects_enabled')
                            ->label('Usar entidad sujeto/animal')
                            ->helperText('Si se desactiva, el recurso de sujetos no aparece en Operativa.'),
                    ]),
                Forms\Components\Section::make('Módulos opcionales')
                    ->schema([
                        Forms\Components\Toggle::make('modules.history_enabled')
                            ->label('Habilitar '.terminology('history', 'historial'))
                            ->helperText('Cada sede debe activarlo por separado. No cambia el flujo mínimo de agenda.'),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Guardar preferencias')
                ->action('save'),
        ];
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $network = Auth::user()?->network;

        if ($network === null) {
            return;
        }

        $network->update([
            'settings' => array_replace_recursive(NetworkSettings::defaults(), $state),
        ]);

        Notification::make()
            ->title('Preferencias operativas guardadas')
            ->success()
            ->send();
    }
}
