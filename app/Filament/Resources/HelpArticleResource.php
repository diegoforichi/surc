<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HelpArticleResource\Pages;
use App\Models\HelpArticle;
use App\Support\Auth\RoleLabels;
use App\Support\Html\VideoEmbed;
use Closure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class HelpArticleResource extends Resource
{
    protected static ?string $model = HelpArticle::class;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationGroup = 'Plataforma';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Artículo de capacitación';

    protected static ?string $pluralModelLabel = 'Capacitación';

    public static function canViewAny(): bool
    {
        return auth()->user()?->is_platform_owner ?? false;
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('title')
                ->label('Título')
                ->required()
                ->live(onBlur: true)
                ->afterStateUpdated(function (Set $set, Get $get, ?string $state): void {
                    if (filled($get('slug')) || ! filled($state)) {
                        return;
                    }

                    $set('slug', Str::slug($state));
                }),
            Forms\Components\TextInput::make('slug')
                ->label('Slug')
                ->required()
                ->unique(ignoreRecord: true),
            Forms\Components\Select::make('category')
                ->label('Categoría')
                ->options(HelpArticle::categoryLabels())
                ->required(),
            Forms\Components\CheckboxList::make('audience_roles')
                ->label('Visible para roles')
                ->helperText('Si no elige ninguno, lo ven todos los usuarios autenticados.')
                ->options(RoleLabels::options([
                    'platform_owner',
                    'network_admin',
                    'organization_admin',
                    'operator',
                    'specialist',
                ]))
                ->columns(2)
                ->columnSpanFull(),
            Forms\Components\Textarea::make('excerpt')
                ->label('Resumen')
                ->helperText('Puede usar tokens {{subject}}, {{history}}, {{organization_plural}} y los demás de la lista cerrada; se reemplazan con la terminología de la red de quien lee.')
                ->rows(2)
                ->columnSpanFull(),
            Forms\Components\RichEditor::make('body')
                ->label('Contenido')
                ->helperText('Tokens: {{organization}}, {{organization_plural}}, {{subject}}, {{subject_plural}}, {{client}}, {{case}}, {{case_plural}}, {{history}}, {{history_entry}}, {{history_types}}.')
                ->columnSpanFull(),
            Forms\Components\TextInput::make('video_url')
                ->label('URL de video')
                ->helperText('YouTube (incl. no listado) o Vimeo. No se aceptan otros sitios.')
                ->url()
                ->rule(function (): Closure {
                    return function (string $attribute, mixed $value, Closure $fail): void {
                        if (! filled($value)) {
                            return;
                        }

                        if (VideoEmbed::embedSrc((string) $value) === null) {
                            $fail('Indique una URL de YouTube o Vimeo.');
                        }
                    };
                })
                ->columnSpanFull(),
            Forms\Components\Toggle::make('is_published')->label('Publicado')->default(false),
            Forms\Components\DateTimePicker::make('published_at')->label('Fecha de publicación'),
            Forms\Components\TextInput::make('sort_order')->label('Orden')->numeric()->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->label('Título')->searchable(),
                Tables\Columns\TextColumn::make('category')
                    ->label('Categoría')
                    ->formatStateUsing(fn (string $state): string => HelpArticle::categoryLabels()[$state] ?? $state)
                    ->badge(),
                Tables\Columns\IconColumn::make('is_published')->label('Publicado')->boolean(),
                Tables\Columns\TextColumn::make('sort_order')->label('Orden')->sortable(),
            ])
            ->defaultSort('sort_order')
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([
                Tables\Actions\DeleteBulkAction::make(),
            ])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHelpArticles::route('/'),
            'create' => Pages\CreateHelpArticle::route('/create'),
            'edit' => Pages\EditHelpArticle::route('/{record}/edit'),
        ];
    }
}
