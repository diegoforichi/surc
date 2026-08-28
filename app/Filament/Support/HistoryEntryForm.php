<?php

namespace App\Filament\Support;

use App\Models\HistoryEntryType;
use App\Models\Subject;
use App\Models\SubjectHistoryEntry;
use App\Support\History\HistoryFieldSchema;
use App\Support\Media\AllowedUploads;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\HtmlString;

class HistoryEntryForm
{
    /**
     * @return array<int, mixed>
     */
    public static function schema(Subject $subject, bool $includeUploads = true): array
    {
        $fields = [
            Forms\Components\Select::make('history_entry_type_id')
                ->label('Tipo')
                ->options(
                    HistoryEntryType::query()
                        ->where('network_id', $subject->network_id)
                        ->where('is_active', true)
                        ->orderBy('sort_order')
                        ->pluck('label', 'id'),
                )
                ->required()
                ->live()
                ->afterStateUpdated(function (Set $set, $state) use ($subject): void {
                    $set('payload', self::reusablePayload($subject, $state ? (int) $state : null));
                }),
            Forms\Components\DateTimePicker::make('occurred_at')
                ->label('Fecha')
                ->default(now())
                ->required(),
            Forms\Components\Textarea::make('summary')
                ->label('Resumen')
                ->helperText('Si lo deja vacío, al finalizar se propone desde hallazgos, producto o resultados.')
                ->columnSpanFull(),
            Forms\Components\Grid::make(2)
                ->schema(function (Get $get) use ($subject): array {
                    $type = HistoryEntryType::query()
                        ->where('network_id', $subject->network_id)
                        ->find($get('history_entry_type_id'));

                    return HistoryFieldSchema::formFields($type?->field_schema);
                })
                ->key('historyPayloadFields')
                ->columnSpanFull(),
        ];

        if ($includeUploads) {
            $fields[] = Forms\Components\FileUpload::make('attachment_files')
                ->label('Adjuntos')
                ->multiple()
                ->disk('local')
                ->visibility('private')
                ->storeFiles(false)
                ->acceptedFileTypes([
                    'application/pdf',
                    'image/jpeg',
                    'image/png',
                    'image/webp',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                ])
                ->maxSize(AllowedUploads::maxKilobytes())
                ->fetchFileInformation(false)
                ->columnSpanFull();
        }

        $fields[] = Forms\Components\Placeholder::make('existing_attachments')
            ->label('Archivos')
            ->content(function (?SubjectHistoryEntry $record): HtmlString|string {
                if ($record === null) {
                    return '—';
                }

                $record->loadMissing('media');
                $links = $record->getMedia('attachments')->map(function ($media) use ($record): string {
                    $url = e(route('history.attachments.show', [$record, $media]));
                    $name = e($media->file_name);

                    return '<a class="text-primary-600 underline" href="'.$url.'" target="_blank" rel="noopener">'.$name.'</a>';
                });

                return $links->isEmpty()
                    ? '—'
                    : new HtmlString($links->implode('<br>'));
            })
            ->visible(fn (?SubjectHistoryEntry $record): bool => $record !== null)
            ->columnSpanFull();

        $fields[] = Forms\Components\Placeholder::make('payload_preview')
            ->label('Datos del registro')
            ->content(function (?SubjectHistoryEntry $record): HtmlString|string {
                if ($record === null) {
                    return '—';
                }

                $record->loadMissing('type');
                $pairs = HistoryFieldSchema::displayPairs($record->type?->field_schema, $record->payload);

                if ($pairs === []) {
                    return '—';
                }

                $rows = collect($pairs)
                    ->map(fn (array $pair): string => '<strong>'.e($pair['label']).':</strong> '.e($pair['value']))
                    ->implode('<br>');

                return new HtmlString($rows);
            })
            ->visible(fn (?SubjectHistoryEntry $record): bool => $record?->exists ?? false)
            ->columnSpanFull();

        $fields[] = Forms\Components\Placeholder::make('addenda_preview')
            ->label('Adendas')
            ->content(function (?SubjectHistoryEntry $record): HtmlString|string {
                if ($record === null) {
                    return '—';
                }

                $record->loadMissing('addenda');

                if ($record->addenda->isEmpty()) {
                    return 'Ninguna';
                }

                $rows = $record->addenda
                    ->sortBy('occurred_at')
                    ->map(function (SubjectHistoryEntry $addendum): string {
                        $when = $addendum->occurred_at?->format('d/m/Y H:i') ?: '—';

                        return e($when).' — '.e($addendum->summary);
                    })
                    ->implode('<br>');

                return new HtmlString($rows);
            })
            ->visible(fn (?SubjectHistoryEntry $record): bool => $record?->exists ?? false)
            ->columnSpanFull();

        return $fields;
    }

    /**
     * @return array<string, mixed>
     */
    public static function reusablePayload(Subject $subject, ?int $typeId): array
    {
        if ($typeId === null) {
            return [];
        }

        $last = $subject->historyEntries()
            ->where('history_entry_type_id', $typeId)
            ->where('status', SubjectHistoryEntry::STATUS_FINAL)
            ->whereNull('addendum_of_id')
            ->orderByDesc('occurred_at')
            ->first();

        if ($last === null) {
            return [];
        }

        $last->loadMissing('type');

        return HistoryFieldSchema::reusableValues($last->type?->field_schema, $last->payload);
    }
}
