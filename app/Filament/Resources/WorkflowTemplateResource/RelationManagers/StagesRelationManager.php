<?php

namespace App\Filament\Resources\WorkflowTemplateResource\RelationManagers;

use App\Enums\RequirementType;
use App\Models\StageRequirement;
use App\Models\WorkflowStage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class StagesRelationManager extends RelationManager
{
    /** @var array<int, string> */
    protected const RESERVED_STAGE_KEYS = [
        'pre_intent',
        'confirmation',
        'summary',
        'consultation',
    ];

    /** @var array<int, string> */
    protected const RESERVED_REQUIREMENT_KEYS = [
        'prior_studies',
        'payment_confirmed',
        'deposit_registered',
        'technical_responsible',
        'summary_loaded',
    ];

    protected static string $relationship = 'stages';

    protected static ?string $title = 'Etapas';

    protected static ?string $modelLabel = 'Etapa';

    protected static ?string $pluralModelLabel = 'Etapas';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('key')
                ->label('Clave')
                ->helperText('Claves reservadas de etapa: pre_intent, confirmation, summary, consultation.')
                ->required()
                ->rule(fn (?WorkflowStage $record): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($record): void {
                    if (! $record instanceof WorkflowStage) {
                        return;
                    }

                    if (! in_array($record->key, self::RESERVED_STAGE_KEYS, true)) {
                        return;
                    }

                    if (! $this->ownerHasCases()) {
                        return;
                    }

                    if ((string) $value !== $record->key) {
                        $fail("No se puede renombrar la clave reservada '{$record->key}' cuando la plantilla ya tiene casos asociados.");
                    }
                }),
            Forms\Components\TextInput::make('label')->label('Etiqueta')->required(),
            Forms\Components\Textarea::make('description')->label('Descripción')->columnSpanFull(),
            Forms\Components\TextInput::make('sort_order')->label('Orden')->numeric()->default(0),
            Forms\Components\Toggle::make('is_terminal')->label('Etapa terminal')->default(false),
            Forms\Components\Repeater::make('requirements')
                ->label('Requisitos')
                ->relationship('requirements')
                ->schema([
                    Forms\Components\TextInput::make('key')
                        ->label('Clave')
                        ->helperText('Claves reservadas de requisito: prior_studies, payment_confirmed, deposit_registered, technical_responsible, summary_loaded.')
                        ->required()
                        ->rule(fn (?StageRequirement $record): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($record): void {
                            if (! $record instanceof StageRequirement) {
                                return;
                            }

                            if (! in_array($record->key, self::RESERVED_REQUIREMENT_KEYS, true)) {
                                return;
                            }

                            if (! $this->ownerHasCases()) {
                                return;
                            }

                            if ((string) $value !== $record->key) {
                                $fail("No se puede renombrar la clave reservada '{$record->key}' cuando la plantilla ya tiene casos asociados.");
                            }
                        }),
                    Forms\Components\TextInput::make('label')->label('Etiqueta')->required(),
                    Forms\Components\Select::make('type')
                        ->label('Tipo')
                        ->options(collect(RequirementType::cases())->mapWithKeys(
                            fn (RequirementType $t) => [$t->value => $t->label()],
                        ))
                        ->required(),
                    Forms\Components\Toggle::make('is_mandatory')->label('Obligatorio')->default(true),
                    Forms\Components\TextInput::make('sort_order')->label('Orden')->numeric()->default(0),
                ])
                ->columnSpanFull()
                ->collapsible()
                ->defaultItems(0),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('key')->label('Clave'),
                Tables\Columns\TextColumn::make('label')->label('Etiqueta'),
                Tables\Columns\TextColumn::make('sort_order')->label('Orden')->sortable(),
                Tables\Columns\IconColumn::make('is_terminal')->label('Terminal')->boolean(),
                Tables\Columns\TextColumn::make('requirements_count')
                    ->label('Requisitos')
                    ->counts('requirements'),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->headerActions([Tables\Actions\CreateAction::make()->label('Nueva etapa')->modalHeading('Nueva etapa')])
            ->actions([
                Tables\Actions\EditAction::make()->label('Editar etapa')->modalHeading('Editar etapa'),
                Tables\Actions\DeleteAction::make()
                    ->before(function (WorkflowStage $record): void {
                        if (! $this->ownerHasCases()) {
                            return;
                        }

                        if (! in_array($record->key, self::RESERVED_STAGE_KEYS, true)) {
                            return;
                        }

                        throw ValidationException::withMessages([
                            'stage' => "No se puede eliminar la etapa reservada '{$record->key}' cuando la plantilla ya tiene casos asociados.",
                        ]);
                    }),
            ]);
    }

    protected function ownerHasCases(): bool
    {
        return $this->getOwnerRecord()->cases()->exists();
    }
}
