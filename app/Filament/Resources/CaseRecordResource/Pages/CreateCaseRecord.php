<?php

namespace App\Filament\Resources\CaseRecordResource\Pages;

use App\Actions\Cases\InitializeCaseWorkflow;
use App\Filament\Resources\CaseRecordResource;
use App\Models\CaseRecord;
use App\Support\Codes\NetworkSequentialCode;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateCaseRecord extends CreateRecord
{
    protected static string $resource = CaseRecordResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = CaseRecordResource::assignNetworkAndOrganization($data);

        if (blank($data['code'] ?? null) && isset($data['network_id'])) {
            $data['code'] = NetworkSequentialCode::generate(
                CaseRecord::class,
                (int) $data['network_id'],
                'CASE',
            );
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        app(InitializeCaseWorkflow::class)->handle($this->record);
        $this->record->refresh();

        if ($this->record->agenda_id) {
            Notification::make()
                ->title('Constancia disponible')
                ->body('Puede imprimir la constancia de inscripción con instrucciones y espacio de firma.')
                ->success()
                ->persistent()
                ->actions([
                    NotificationAction::make('print')
                        ->label('Imprimir constancia')
                        ->url(route('cases.ticket', $this->record), shouldOpenInNewTab: true),
                ])
                ->send();
        }
    }
}
