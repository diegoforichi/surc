<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class SurcImportDataCommand extends Command
{
    protected $signature = 'surc:import-data
        {--path= : Ruta al bundle exportado}
        {--truncate : Limpia tablas de destino antes de importar}';

    protected $description = 'Importa un bundle SURC (JSON + storage) preservando IDs';

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
        try {
            $bundlePath = $this->resolveBundlePath($this->option('path'));
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $dataPath = $bundlePath.DIRECTORY_SEPARATOR.'data';

        if (! File::isDirectory($dataPath)) {
            $this->error("No se encontró carpeta data en bundle: {$bundlePath}");

            return self::FAILURE;
        }

        if (! (bool) $this->option('truncate')) {
            $this->warn('Importar sin --truncate puede generar duplicados o conflictos de PK.');
            if (! $this->confirm('¿Continuar sin truncar?', false)) {
                $this->line('Operación cancelada.');

                return self::SUCCESS;
            }
        }

        Schema::disableForeignKeyConstraints();
        DB::beginTransaction();

        try {
            if ((bool) $this->option('truncate')) {
                $this->truncateDestinationTables();
            }

            foreach (self::TABLES as $table) {
                if (! Schema::hasTable($table)) {
                    continue;
                }

                $filePath = $dataPath.DIRECTORY_SEPARATOR."{$table}.json";
                if (! File::exists($filePath)) {
                    continue;
                }

                /** @var array<int, array<string, mixed>> $rows */
                $rows = json_decode(File::get($filePath), true) ?? [];

                if ($rows === []) {
                    continue;
                }

                foreach (array_chunk($rows, 500) as $chunk) {
                    DB::table($table)->insert($chunk);
                }

                $this->line(" - {$table}: ".count($rows).' fila(s) importada(s)');
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Schema::enableForeignKeyConstraints();

            $this->error('Falló la importación: '.$e->getMessage());

            return self::FAILURE;
        }

        Schema::enableForeignKeyConstraints();

        $storagePath = $bundlePath.DIRECTORY_SEPARATOR.'storage-app';
        if (File::isDirectory($storagePath)) {
            File::copyDirectory($storagePath, storage_path('app'));
            $this->line(' - Archivos de storage/app restaurados');
        }

        $this->newLine();
        $this->info('Import finalizado correctamente.');

        return self::SUCCESS;
    }

    protected function truncateDestinationTables(): void
    {
        foreach (array_reverse(self::TABLES) as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            DB::table($table)->truncate();
        }
    }

    protected function resolveBundlePath(?string $pathOption): string
    {
        if ($pathOption !== null && $pathOption !== '') {
            return $this->isAbsolutePath($pathOption)
                ? $pathOption
                : base_path($pathOption);
        }

        $defaultBase = storage_path('app/surc-export');
        if (! File::isDirectory($defaultBase)) {
            throw new \RuntimeException('No se encontró carpeta de exportaciones en storage/app/surc-export');
        }

        $directories = collect(File::directories($defaultBase))
            ->sortByDesc(fn (string $directory) => File::lastModified($directory))
            ->values();

        if ($directories->isEmpty()) {
            throw new \RuntimeException('No hay bundles de exportación disponibles.');
        }

        return $directories->first();
    }

    protected function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
    }
}
