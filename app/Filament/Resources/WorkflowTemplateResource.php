<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasNetworkFormFields;
use App\Filament\Concerns\ScopesToUserNetwork;
use App\Filament\Resources\WorkflowTemplateResource\Pages;
use App\Filament\Resources\WorkflowTemplateResource\RelationManagers\StagesRelationManager;
use App\Models\WorkflowTemplate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class WorkflowTemplateResource extends Resource
{
    use HasNetworkFormFields;
    use ScopesToUserNetwork;

    protected static ?string $model = WorkflowTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Plantilla de flujo';

    protected static ?string $pluralModelLabel = 'Plantillas de flujo';

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('config.manage') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            ...self::networkIdFormFields(),
            Forms\Components\TextInput::make('name')->label('Nombre')->required(),
            Forms\Components\Toggle::make('is_default')->label('Por defecto')->default(false),
            Forms\Components\Toggle::make('is_active')->label('Activo')->default(true),
            Forms\Components\Textarea::make('instructions')
                ->label('Instrucciones de la constancia')
                ->helperText('Texto heredado por las agendas de este flujo. Puede ajustarse en cada agenda.')
                ->columnSpanFull(),
            Forms\Components\Textarea::make('consent_text')
                ->label('Consentimiento / condiciones')
                ->helperText('Texto para firma en papel en la constancia de inscripción.')
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nombre')->searchable(),
                Tables\Columns\IconColumn::make('is_default')->label('Por defecto')->boolean(),
                Tables\Columns\IconColumn::make('is_active')->label('Activo')->boolean(),
                Tables\Columns\TextColumn::make('stages_count')->label('Etapas')->counts('stages'),
            ])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([
                Tables\Actions\DeleteBulkAction::make(),
            ])]);
    }

    public static function getRelations(): array
    {
        return [StagesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWorkflowTemplates::route('/'),
            'create' => Pages\CreateWorkflowTemplate::route('/create'),
            'edit' => Pages\EditWorkflowTemplate::route('/{record}/edit'),
        ];
    }
}
