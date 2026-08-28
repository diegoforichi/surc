<?php

namespace App\Actions\Parties;

use App\Enums\ActorCategory;
use App\Models\ActorType;
use App\Models\Party;
use Illuminate\Validation\ValidationException;

class FindOrCreateClientParty
{
    /**
     * @param  array{display_name: string, document_id?: ?string, phone?: ?string, email?: ?string, whatsapp?: ?string}  $data
     */
    public function handle(int $networkId, int $organizationId, array $data): Party
    {
        $type = ActorType::query()
            ->where('network_id', $networkId)
            ->where('category', ActorCategory::Client)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->first();

        if ($type === null) {
            throw ValidationException::withMessages([
                'display_name' => 'No hay un tipo de '.strtolower((string) terminology('client', 'propietario')).' activo en esta red.',
            ]);
        }

        $payload = [
            'display_name' => trim((string) ($data['display_name'] ?? '')),
            'document_id' => self::nullableTrim($data['document_id'] ?? null),
            'phone' => self::nullableTrim($data['phone'] ?? null),
            'email' => self::nullableTrim($data['email'] ?? null),
            'whatsapp' => self::nullableTrim($data['whatsapp'] ?? null),
        ];

        if ($payload['display_name'] === '') {
            throw ValidationException::withMessages([
                'display_name' => 'Indique el nombre.',
            ]);
        }

        $existing = $this->existing($networkId, $organizationId, $type->id, $payload);

        if ($existing !== null) {
            return $existing;
        }

        return Party::query()->create([
            'network_id' => $networkId,
            'organization_id' => $organizationId,
            'actor_type_id' => $type->id,
            'display_name' => $payload['display_name'],
            'document_id' => $payload['document_id'],
            'phone' => $payload['phone'],
            'email' => $payload['email'],
            'whatsapp' => $payload['whatsapp'],
            'is_active' => true,
        ]);
    }

    /**
     * @param  array{display_name: string, document_id: ?string, phone: ?string, email: ?string, whatsapp: ?string}  $payload
     */
    protected function existing(int $networkId, int $organizationId, int $actorTypeId, array $payload): ?Party
    {
        $query = Party::query()
            ->where('network_id', $networkId)
            ->where('organization_id', $organizationId)
            ->where('actor_type_id', $actorTypeId);

        if ($payload['document_id'] !== null) {
            return (clone $query)->where('document_id', $payload['document_id'])->first();
        }

        if ($payload['phone'] !== null) {
            return (clone $query)
                ->where('display_name', $payload['display_name'])
                ->where('phone', $payload['phone'])
                ->first();
        }

        return null;
    }

    protected static function nullableTrim(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
