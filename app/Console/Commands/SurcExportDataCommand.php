<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SurcExportDataCommand extends Command
{
    protected $signature = 'surc:export-data {--path=storage/app/surc-export : Directorio base donde generar el bundle}';

    protected $description = 'Exporta datos SURC en JSON portable junto con archivos de storage/app';

    /** @var array<int, string> */
    protected const TABLES = [
        'networks',
        'organizations',
        'users',
        'terminology',
        'actor_types',
        'workflow_templates',
        'workflow_stages',
        'stage_requirements',
        'custom_field_definitions',
        'parties',
        'subjects',
        'agendas',
        'cases',
        'case_parties',
        'case_stage_status',
        'case_requirement_completions',
        'case_events',
        'payments',
        'public_content',
        'import_batches',
        'media',
        'model_has_roles',
        'history_entry_types',
        'subject_history_entries',
        'case_history_shares',
    ];

    public function handle(): int
    {
        $basePath = $this->resolvePath((string) $this->option('path'));
        $bundlePath = $basePath.DIRECTORY_SEPARATOR.now()->format('Ymd_His');
        $dataPath = $bundlePath.DIRECTORY_SEPARATOR.'data';
        $storageBundlePath = $bundlePath.DIRECTORY_SEPARATOR.'storage-app';

        File::ensureDirectoryExists($dataPath);
        File::ensureDirectoryExists($storageBundlePath);

        $this->info("Exportando bundle en: {$bundlePath}");

        $exportedTables = [];
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $rows = $this->readTable($table);
            File::put(
                $dataPath.DIRECTORY_SEPARATOR."{$table}.json",
                json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );

            $exportedTables[$table] = count($rows);
            $this->line(" - {$table}: ".count($rows).' fila(s)');
        }

        $this->copyStorageApp($bundlePath, $storageBundlePath);

        $metadata = [
            'exported_at' => now()->toIso8601String(),
            'app_env' => config('app.env'),
            'db_connection' => config('database.default'),
            'tables' => $exportedTables,
        ];
        File::put(
            $bundlePath.DIRECTORY_SEPARATOR.'metadata.json',
            json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        $this->newLine();
        $this->info('Export finalizado.');
        $this->line("Bundle listo en: {$bundlePath}");

        return self::SUCCESS;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function readTable(string $table): array
    {
        $query = DB::table($table);

        if (Schema::hasColumn($table, 'id')) {
            $query->orderBy('id');
        }

        return $query->get()->map(fn ($row) => (array) $row)->all();
    }

    protected function copyStorageApp(string $bundlePath, string $storageBundlePath): void
    {
        $source = storage_path('app');

        if (! File::isDirectory($source)) {
            return;
        }

        foreach (File::allFiles($source) as $file) {
            $fullPath = $file->getPathname();

            if (Str::startsWith($fullPath, $bundlePath)) {
                continue;
            }

            $relativePath = Str::after($fullPath, $source.DIRECTORY_SEPARATOR);

            if (Str::startsWith($relativePath, 'surc-export'.DIRECTORY_SEPARATOR) || Str::contains($relativePath, DIRECTORY_SEPARATOR.'surc-export'.DIRECTORY_SEPARATOR)) {
                continue;
            }
            $targetPath = $storageBundlePath.DIRECTORY_SEPARATOR.$relativePath;
            File::ensureDirectoryExists(dirname($targetPath));
            File::copy($fullPath, $targetPath);
        }
    }

    protected function resolvePath(string $path): string
    {
        if ($path === '') {
            return storage_path('app/surc-export');
        }

        if ($this->isAbsolutePath($path)) {
            return $path;
        }

        return base_path($path);
    }

    protected function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
    }
}
