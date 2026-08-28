<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasNetworkFormFields;
use App\Filament\Concerns\ScopesToUserNetwork;
use App\Filament\Resources\PublicContentResource\Pages;
use App\Filament\Support\PublicImageUpload;
use App\Models\PublicContent;
use App\Support\Labels\OperationalStatusLabels;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

class PublicContentResource extends Resource
{
    use HasNetworkFormFields;
    use ScopesToUserNetwork;

    protected static ?string $model = PublicContent::class;

    protected static ?string $navigationIcon = 'heroicon-o-newspaper';

    protected static ?string $navigationGroup = 'Sitio público';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Contenido público';

    protected static ?string $pluralModelLabel = 'Contenidos públicos';

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('public.manage') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('public.manage') ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->can('public.manage') ?? false;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->can('public.manage') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            ...self::networkIdFormFields(),
            Forms\Components\Select::make('type')
                ->label('Tipo')
                ->options([
                    'carousel' => 'Carrusel',
                    'blog' => 'Blog',
                    'page' => 'Página',
                ])
                ->required()
                ->live(),
            Forms\Components\TextInput::make('title')->label('Título')->required(),
            Forms\Components\TextInput::make('slug')
                ->label('Slug')
                ->helperText('Obligatorio en blog y páginas. Debe ser único en la red para ese tipo.')
                ->required(fn (Get $get): bool => in_array($get('type'), ['blog', 'page'], true))
                ->unique(
                    ignoreRecord: true,
                    modifyRuleUsing: function (Unique $rule, Get $get): Unique {
                        return $rule
                            ->where('network_id', $get('network_id') ?? auth()->user()?->network_id)
                            ->where('type', $get('type'));
                    },
                ),
            Forms\Components\RichEditor::make('body')
                ->label('Contenido')
                ->columnSpanFull(),
            Forms\Components\Textarea::make('excerpt')->label('Resumen')->rows(2),
            Forms\Components\TextInput::make('seo_description')
                ->label('Descripción SEO')
                ->maxLength(180),
            PublicImageUpload::make('image_path', 'public-content', 'Imagen'),
            Forms\Components\Toggle::make('is_published')->label('Publicado')->default(false)->live(),
            Forms\Components\DateTimePicker::make('published_at')
                ->label('Fecha de publicación')
                ->seconds(false)
                ->visible(fn (Get $get): bool => (bool) $get('is_published')),
            Forms\Components\TextInput::make('sort_order')->label('Orden')->numeric()->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => OperationalStatusLabels::publicContentType($state)),
                Tables\Columns\TextColumn::make('title')->label('Título')->searchable(),
                Tables\Columns\TextColumn::make('slug')->label('Identificador URL'),
                Tables\Columns\IconColumn::make('is_published')->label('Publicado')->boolean(),
                Tables\Columns\TextColumn::make('published_at')
                    ->label('Publicado el')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('sort_order')->label('Orden')->sortable(),
            ])
            ->defaultSort('sort_order')
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([
                Tables\Actions\DeleteBulkAction::make(),
            ])]);
    }

    public static function assignPublishedAt(array $data): array
    {
        if (($data['is_published'] ?? false) && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        return $data;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPublicContents::route('/'),
            'create' => Pages\CreatePublicContent::route('/create'),
            'edit' => Pages\EditPublicContent::route('/{record}/edit'),
        ];
    }
}
