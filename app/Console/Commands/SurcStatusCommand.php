<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesSurcContext;
use App\Domain\Templates\IndustryTemplateRegistry;
use App\Models\ActorType;
use App\Models\Agenda;
use App\Models\CaseRecord;
use App\Models\Network;
use App\Models\Organization;
use App\Models\Party;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\DB;

class SurcStatusCommand extends Command
{
    use ResolvesSurcContext;

    protected $signature = 'surc:status {network? : ID o slug opcional de la red}';

    protected $description = 'Muestra un inventario operativo de redes y configuración';

    public function handle(): int
    {
        $this->checkPendingMigrations();

        $networkFilter = $this->argument('network');

        if ($networkFilter) {
            $network = $this->resolveNetwork((string) $networkFilter, askWhenMissing: false);
            $networks = collect([$network]);
        } else {
            $networks = Network::query()->orderBy('name')->get();
        }

        if ($networks->isEmpty()) {
            $this->warn('No hay redes registradas.');

            return self::SUCCESS;
        }

        foreach ($networks as $network) {
            $this->renderNetworkSummary($network);
        }

        return self::SUCCESS;
    }

    protected function checkPendingMigrations(): void
    {
        try {
            /** @var Migrator $migrator */
            $migrator = $this->laravel->make('migrator');
            $migrator->setConnection(config('database.default'));

            $ran = $migrator->getRepository()->getRan();

            $pending = collect($migrator->getMigrationFiles(database_path('migrations')))
                ->keys()
                ->filter(fn (string $migration) => ! in_array($migration, $ran, true));

            if ($pending->isNotEmpty()) {
                $this->newLine();
                $this->warn('ADVERTENCIA: hay ' . $pending->count() . ' migración(es) pendiente(s).');
                $this->warn('Ejecutar antes de continuar: php artisan migrate --force');
                foreach ($pending as $migration) {
                    $this->line("  - {$migration}");
                }
                $this->newLine();
            }
        } catch (\Throwable) {
            $this->warn('No se pudo verificar el estado de migraciones.');
        }
    }

    protected function renderNetworkSummary(Network $network): void
    {
        $this->newLine();
        $this->info("Red: {$network->name} ({$network->slug})");
        $this->line(sprintf(
            'Template: %s | Activa: %s',
            $this->templateLabel($network),
            $network->is_active ? 'Sí' : 'No',
        ));

        $organizationsCount = Organization::query()->where('network_id', $network->id)->count();
        $usersCount = User::query()->where('network_id', $network->id)->count();
        $actorsCount = Party::query()->where('network_id', $network->id)->count();
        $subjectsCount = Subject::query()->where('network_id', $network->id)->count();
        $casesCount = CaseRecord::query()->where('network_id', $network->id)->count();
        $agendasCount = Agenda::query()->where('network_id', $network->id)->count();

        $this->line("Conteos => Sedes: {$organizationsCount}, Usuarios: {$usersCount}, Actores: {$actorsCount}, Sujetos: {$subjectsCount}, Casos: {$casesCount}, Agendas: {$agendasCount}");

        $organizations = Organization::query()
            ->where('network_id', $network->id)
            ->withCount(['users', 'parties', 'cases'])
            ->orderBy('name')
            ->get();

        if ($organizations->isNotEmpty()) {
            $this->table(
                ['Sede', 'Slug', 'Activa', 'Usuarios', 'Actores', 'Casos'],
                $organizations->map(fn (Organization $organization) => [
                    $organization->name,
                    $organization->slug,
                    $organization->is_active ? 'Sí' : 'No',
                    $organization->users_count,
                    $organization->parties_count,
                    $organization->cases_count,
                ])->all()
            );
        }

        $users = User::query()
            ->where('network_id', $network->id)
            ->with('roles')
            ->orderBy('name')
            ->get();

        if ($users->isNotEmpty()) {
            $this->table(
                ['Usuario', 'Email', 'Rol(es)', 'Owner', 'Activo'],
                $users->map(fn (User $user) => [
                    $user->name,
                    $user->email,
                    $user->getRoleNames()->implode(', ') ?: '-',
                    $user->is_platform_owner ? 'Sí' : 'No',
                    $user->is_active ? 'Sí' : 'No',
                ])->all()
            );
        }

        $actorsByType = ActorType::query()
            ->where('network_id', $network->id)
            ->withCount('parties')
            ->orderBy('sort_order')
            ->get();

        if ($actorsByType->isNotEmpty()) {
            $this->table(
                ['Tipo actor', 'Clave', 'Categoría', 'Actores'],
                $actorsByType->map(fn (ActorType $type) => [
                    $type->label,
                    $type->key,
                    $type->category?->value ?? '-',
                    $type->parties_count,
                ])->all()
            );
        }
    }

    protected function templateLabel(Network $network): string
    {
        $key = (string) $network->industry_template_key;
        $registry = app(IndustryTemplateRegistry::class);

        if ($key === '') {
            return '-';
        }

        if (! $registry->has($key)) {
            return $key;
        }

        return "{$registry->find($key)->name} ({$key})";
    }
}
