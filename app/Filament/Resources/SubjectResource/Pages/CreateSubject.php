<?php

namespace App\Filament\Resources\SubjectResource\Pages;

use App\Filament\Resources\SubjectResource;
use App\Models\Subject;
use App\Support\Codes\NetworkSequentialCode;
use Filament\Resources\Pages\CreateRecord;

class CreateSubject extends CreateRecord
{
    protected static string $resource = SubjectResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = SubjectResource::assignNetworkAndOrganization($data);

        if (blank($data['code'] ?? null) && isset($data['network_id'])) {
            $data['code'] = NetworkSequentialCode::generate(
                Subject::class,
                (int) $data['network_id'],
                'AN',
            );
        }

        return $data;
    }
}
