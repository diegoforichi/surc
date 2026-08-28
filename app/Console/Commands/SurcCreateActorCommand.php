<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesSurcContext;
use App\Models\ActorType;
use App\Models\Party;
use App\Models\User;
use App\Models\WorkflowTemplate;
use Illuminate\Console\Command;

class SurcCreateActorCommand extends Command
{
    use ResolvesSurcContext;

    protected $signature = 'surc:create-actor
        {network? : ID o slug de red}
        {type? : key o id de tipo de actor}
        {name? : Nombre visible del actor}
        {--org= : ID o slug de sede}
        {--link-user= : ID o email del usuario a vincular}
        {--workflow= : ID o nombre de plantilla de flujo por defecto}
        {--email= : Email del actor}
        {--phone= : Teléfono del actor}
        {--document= : Documento}
        {--inactive : Crear actor desactivado}';

    protected $description = 'Crea un actor (especialista/profesional/cliente) en una red';

    public function handle(): int
    {
        $network = $this->resolveNetwork($this->argument('network'));
        $actorType = $this->resolveActorType($network->id, $this->argument('type'));
        $name = (string) ($this->argument('name') ?: $this->ask('Nombre del actor'));

        $organization = $this->resolveOrganization($network, $this->option('org'));

        $userId = $this->resolveLinkedUserId($network->id, $actorType, $this->option('link-user'));
        $workflowId = $this->resolveWorkflowId($network->id, $this->option('workflow'));

        $party = Party::create([
            'network_id' => $network->id,
            'actor_type_id' => $actorType->id,
            'organization_id' => $organization?->id,
            'user_id' => $userId,
            'default_workflow_template_id' => $workflowId,
            'display_name' => $name,
            'document_id' => $this->option('document'),
            'email' => $this->option('email'),
            'phone' => $this->option('phone'),
            'is_active' => ! (bool) $this->option('inactive'),
        ]);

        $this->info("Actor creado: {$party->display_name}");
        $this->line("Tipo: {$actorType->label} ({$actorType->key})");
        if ($organization !== null) {
            $this->line("Sede: {$organization->name}");
        }
        if ($userId !== null) {
            $this->line("Usuario vinculado ID: {$userId}");
        }
        if ($workflowId !== null) {
            $this->line("Workflow por defecto ID: {$workflowId}");
        }

        return self::SUCCESS;
    }

    protected function resolveActorType(int $networkId, mixed $input): ActorType
    {
        if ($input !== null && $input !== '') {
            return ActorType::query()
                ->where('network_id', $networkId)
                ->where(function ($query) use ($input): void {
                    $query->where('id', $input)
                        ->orWhere('key', $input);
                })
                ->firstOrFail();
        }

        $types = ActorType::query()
            ->where('network_id', $networkId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $options = $types->mapWithKeys(fn (ActorType $type) => [
            $type->id => "{$type->label} ({$type->key})",
        ])->all();
        $selected = $this->choice('Tipo de actor', $options, array_key_first($options));

        return $types->firstWhere('id', (int) $selected) ?? $types->firstOrFail();
    }

    protected function resolveLinkedUserId(int $networkId, ActorType $actorType, mixed $input): ?int
    {
        if (! $actorType->is_user_linkable) {
            return null;
        }

        if ($input === null || $input === '') {
            if (! $this->confirm('¿Desea vincular un usuario?', false)) {
                return null;
            }

            $input = (string) $this->ask('Ingrese ID o email del usuario');
        }

        $user = User::query()
            ->where('network_id', $networkId)
            ->where(function ($query) use ($input): void {
                $query->where('id', $input)
                    ->orWhere('email', $input);
            })
            ->firstOrFail();

        return $user->id;
    }

    protected function resolveWorkflowId(int $networkId, mixed $input): ?int
    {
        if ($input === null || $input === '') {
            return null;
        }

        $workflow = WorkflowTemplate::query()
            ->where('network_id', $networkId)
            ->where(function ($query) use ($input): void {
                $query->where('id', $input)
                    ->orWhere('name', $input);
            })
            ->firstOrFail();

        return $workflow->id;
    }
}
