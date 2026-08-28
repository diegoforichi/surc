<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ScopesToUserNetwork;
use App\Filament\Resources\ImportBatchResource\Pages;
use App\Models\ImportBatch;
use App\Support\Labels\OperationalStatusLabels;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ImportBatchResource extends Resource
{
    use ScopesToUserNetwork;

    protected static ?string $model = ImportBatch::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static ?string $navigationGroup = 'Importación';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Lote de importación';

    protected static ?string $pluralModelLabel = 'Lotes de importación';

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('imports.run') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('imports.run') ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->can('imports.run') ?? false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('target')
                ->label('Destino')
                ->options([
                    'subjects' => terminology('subject', 'Sujetos'),
                    'parties' => 'Actores',
                    'cases' => terminology('case', 'Casos'),
                ])
                ->required()
                ->visible(fn (string $operation): bool => $operation === 'create'),
            Forms\Components\FileUpload::make('file_path')
                ->label('Archivo CSV')
                ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])
                ->directory('imports')
                ->required()
                ->visible(fn (string $operation): bool => $operation === 'create'),
            Forms\Components\Select::make('organization_id')
                ->label(terminology('organization', 'Sede'))
                ->relationship(
                    'organization',
                    'name',
                    fn (Builder $query) => ImportBatchResource::scopeOrganizations($query),
                )
                ->default(fn (): ?int => auth()->user()?->fixedOrganizationId())
                ->disabled(fn (): bool => auth()->user()?->fixedOrganizationId() !== null)
                ->dehydrated()
                ->searchable()
                ->preload()
                ->visible(fn (string $operation): bool => $operation === 'create'),
            Forms\Components\TextInput::make('status')
                ->label('Estado')
                ->formatStateUsing(fn (?string $state): string => OperationalStatusLabels::import((string) $state))
                ->disabled()
                ->visible(fn (string $operation): bool => $operation !== 'create'),
            Forms\Components\TextInput::make('rows_total')
                ->label('Filas totales')
                ->disabled()
                ->visible(fn (string $operation): bool => $operation !== 'create'),
            Forms\Components\TextInput::make('rows_ok')
                ->label('Filas correctas')
                ->disabled()
                ->visible(fn (string $operation): bool => $operation !== 'create'),
            Forms\Components\TextInput::make('rows_failed')
                ->label('Filas con error')
                ->disabled()
                ->visible(fn (string $operation): bool => $operation !== 'create'),
            Forms\Components\Textarea::make('errors')
                ->label('Errores')
                ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : $state)
                ->disabled()
                ->columnSpanFull()
                ->visible(fn (string $operation): bool => $operation !== 'create'),
        ]);
    }

    public static function scopeOrganizations(Builder $query): Builder
    {
        $user = auth()->user();

        if ($user?->is_platform_owner) {
            return $query;
        }

        if ($user?->network_id) {
            $query->where('network_id', $user->network_id);
        } else {
            return $query->whereRaw('1 = 0');
        }

        $fixed = $user->fixedOrganizationId();

        if ($fixed !== null) {
            $query->where('id', $fixed);
        }

        return $query;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('target')
                    ->label('Destino')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => OperationalStatusLabels::importTarget($state)),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => OperationalStatusLabels::import($state)),
                Tables\Columns\TextColumn::make('rows_ok')->label('Correctas'),
                Tables\Columns\TextColumn::make('rows_failed')->label('Errores'),
                Tables\Columns\TextColumn::make('user.name')->label('Usuario'),
                Tables\Columns\TextColumn::make('created_at')->label('Creado')->dateTime(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([Tables\Actions\EditAction::make()->label('Ver')])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListImportBatches::route('/'),
            'create' => Pages\CreateImportBatch::route('/create'),
            'edit' => Pages\EditImportBatch::route('/{record}/edit'),
        ];
    }
}
