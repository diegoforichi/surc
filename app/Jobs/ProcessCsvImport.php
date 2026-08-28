<?php

namespace App\Jobs;

use App\Models\ImportBatch;
use App\Models\Party;
use App\Models\Subject;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use League\Csv\Reader;

class ProcessCsvImport implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public ImportBatch $batch) {}

    public function handle(): void
    {
        $this->batch->update(['status' => 'processing']);

        $path = Storage::disk('local')->path($this->batch->file_path);
        $csv = Reader::createFromPath($path, 'r');
        $csv->setHeaderOffset(0);

        $errors = [];
        $ok = 0;
        $failed = 0;
        $rows = iterator_to_array($csv->getRecords());
        $total = count($rows);

        foreach ($rows as $index => $row) {
            try {
                match ($this->batch->target) {
                    'subjects' => $this->importSubject($row),
                    'parties' => $this->importParty($row),
                    default => throw new \RuntimeException('Destino no soportado en esta versión.'),
                };
                $ok++;
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = ['row' => $index + 2, 'message' => $e->getMessage()];
            }
        }

        $this->batch->update([
            'status' => $failed > 0 ? 'completed_with_errors' : 'completed',
            'rows_total' => $total,
            'rows_ok' => $ok,
            'rows_failed' => $failed,
            'errors' => $errors,
        ]);
    }

    protected function importSubject(array $row): void
    {
        Subject::create([
            'network_id' => $this->batch->network_id,
            'organization_id' => $this->batch->organization_id,
            'label_name' => $row['label_name'] ?? $row['nombre'] ?? 'Sin nombre',
            'code' => $row['code'] ?? $row['codigo'] ?? null,
            'metadata' => collect($row)->except(['label_name', 'nombre', 'code', 'codigo'])->filter()->all(),
        ]);
    }

    protected function importParty(array $row): void
    {
        Party::create([
            'network_id' => $this->batch->network_id,
            'organization_id' => $this->batch->organization_id,
            'actor_type_id' => $row['actor_type_id'] ?? throw new \RuntimeException('actor_type_id requerido'),
            'display_name' => $row['display_name'] ?? $row['nombre'] ?? 'Sin nombre',
            'email' => $row['email'] ?? null,
            'phone' => $row['phone'] ?? $row['telefono'] ?? null,
            'metadata' => collect($row)->except(['actor_type_id', 'display_name', 'nombre', 'email', 'phone', 'telefono'])->filter()->all(),
        ]);
    }
}
