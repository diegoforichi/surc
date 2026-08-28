<?php

namespace App\Filament\Resources\WorkflowTemplateResource\Pages;

use App\Filament\Resources\WorkflowTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateWorkflowTemplate extends CreateRecord
{
    protected static string $resource = WorkflowTemplateResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return WorkflowTemplateResource::assignNetworkId($data);
    }
}
