<?php

namespace App\Filament\Pages;

use App\Filament\Support\NetworkInstitutionalForm;
use App\Models\Network;
use Filament\Actions\Action;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class InstitutionalProfile extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-building-library';

    protected static ?string $navigationGroup = 'Sitio público';

    protected static ?int $navigationSort = 0;

    protected static ?string $slug = 'datos-institucionales';

    protected static ?string $title = 'Datos institucionales';

    protected static ?string $navigationLabel = 'Datos institucionales';

    protected static string $view = 'filament.pages.institutional-profile';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return ($user?->can('public.manage') ?? false)
            && $user?->network_id !== null;
    }

    public function mount(): void
    {
        $network = $this->network();

        abort_unless($network instanceof Network, 404);

        $this->form->fill($network->only([
            'logo_path',
            'cover_path',
            'slogan',
            'description',
            'phone',
            'email',
            'whatsapp',
            'address',
        ]));
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema(NetworkInstitutionalForm::schema())
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Guardar')
                ->action('save'),
        ];
    }

    public function save(): void
    {
        $network = $this->network();

        if ($network === null) {
            return;
        }

        $network->update($this->form->getState());

        Notification::make()
            ->title('Datos institucionales guardados')
            ->success()
            ->send();
    }

    protected function network(): ?Network
    {
        return Auth::user()?->network;
    }
}
