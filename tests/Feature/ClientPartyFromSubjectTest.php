<?php

namespace Tests\Feature;

use App\Actions\Parties\FindOrCreateClientParty;
use App\Enums\ActorCategory;
use App\Filament\Resources\PartyResource;
use App\Filament\Resources\SubjectResource;
use App\Filament\Resources\SubjectResource\Pages\CreateSubject;
use App\Models\ActorType;
use App\Models\Party;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsSurcFixtures;
use Tests\TestCase;

class ClientPartyFromSubjectTest extends TestCase
{
    use BuildsSurcFixtures;
    use RefreshDatabase;

    public function test_creates_client_in_user_clinic(): void
    {
        [$context, $admin, $clientType] = $this->clinicWithClientType();
        $this->actingAs($admin);

        $party = app(FindOrCreateClientParty::class)->handle(
            $context['network']->id,
            $context['organization']->id,
            [
                'display_name' => 'Juan Pérez',
                'document_id' => '12345678',
                'phone' => '099111222',
            ],
        );

        $this->assertSame($clientType->id, $party->actor_type_id);
        $this->assertSame($context['organization']->id, $party->organization_id);
        $this->assertSame('12345678', $party->document_id);
    }

    public function test_reuses_same_document_in_the_same_clinic(): void
    {
        [$context, $admin] = $this->clinicWithClientType();
        $this->actingAs($admin);

        $action = app(FindOrCreateClientParty::class);
        $first = $action->handle($context['network']->id, $context['organization']->id, [
            'display_name' => 'Juan Pérez',
            'document_id' => '12345678',
        ]);
        $second = $action->handle($context['network']->id, $context['organization']->id, [
            'display_name' => 'Juan P.',
            'document_id' => '12345678',
        ]);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Party::query()->where('document_id', '12345678')->count());
    }

    public function test_same_document_in_another_clinic_creates_another_party(): void
    {
        [$context, $admin] = $this->clinicWithClientType();
        $other = $context['organization']->replicate();
        $other->slug = 'otra-dueno';
        $other->name = 'Otra dueño';
        $other->save();

        $action = app(FindOrCreateClientParty::class);
        $first = $action->handle($context['network']->id, $context['organization']->id, [
            'display_name' => 'Juan Pérez',
            'document_id' => '12345678',
        ]);
        $second = $action->handle($context['network']->id, $other->id, [
            'display_name' => 'Juan Pérez',
            'document_id' => '12345678',
        ]);

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame($other->id, $second->organization_id);

        $this->actingAs($admin);
        $this->assertFalse(
            PartyResource::getEloquentQuery()->whereKey($second->id)->exists()
        );
    }

    public function test_subject_form_keeps_created_owner_and_lists_siblings(): void
    {
        [$context, $admin] = $this->clinicWithClientType();
        $this->actingAs($admin);

        $owner = app(FindOrCreateClientParty::class)->handle(
            $context['network']->id,
            $context['organization']->id,
            ['display_name' => 'Ana Dueña', 'document_id' => '999'],
        );

        Livewire::test(CreateSubject::class)
            ->fillForm([
                'label_name' => 'Luna',
                'owner_party_id' => $owner->id,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $luna = Subject::query()->where('label_name', 'Luna')->first();
        $this->assertNotNull($luna);
        $this->assertSame($owner->id, $luna->owner_party_id);
        $this->assertSame($context['organization']->id, $luna->organization_id);

        Subject::create([
            'network_id' => $context['network']->id,
            'organization_id' => $context['organization']->id,
            'owner_party_id' => $owner->id,
            'label_name' => 'Sol',
            'is_active' => true,
        ]);

        $this->assertSame(
            PartyResource::getUrl('edit', ['record' => $owner]),
            SubjectResource::ownerUrl($luna),
        );
        $this->assertSame(2, $owner->ownedSubjects()->count());
    }

    /**
     * @return array{0: array<string, mixed>, 1: \App\Models\User, 2: ActorType}
     */
    protected function clinicWithClientType(): array
    {
        $this->seedRoles();
        $context = $this->createNetworkContext('red-duenos');
        $clientType = ActorType::create([
            'network_id' => $context['network']->id,
            'key' => 'client',
            'label' => 'Propietario',
            'category' => ActorCategory::Client,
            'is_user_linkable' => false,
            'show_in_directory' => false,
            'sort_order' => 2,
            'is_active' => true,
        ]);
        $admin = $this->createUserWithRole(
            $context['network'],
            'organization_admin',
            $context['organization'],
            'oa-dueno@test.com',
        );

        return [$context, $admin, $clientType];
    }
}
