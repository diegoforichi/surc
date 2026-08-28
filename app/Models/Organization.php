<?php

namespace App\Models;

use App\Support\Contact\WhatsappLink;
use App\Support\Tenancy\BelongsToNetwork;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    use BelongsToNetwork;

    protected $fillable = [
        'network_id',
        'name',
        'slug',
        'address',
        'phone',
        'whatsapp',
        'email',
        'website',
        'description',
        'photo_path',
        'is_active',
        'show_in_directory',
        'history_enabled',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'is_active' => 'boolean',
            'show_in_directory' => 'boolean',
            'history_enabled' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function parties(): HasMany
    {
        return $this->hasMany(Party::class);
    }

    public function cases(): HasMany
    {
        return $this->hasMany(CaseRecord::class);
    }

    public function whatsappUrl(): ?string
    {
        return WhatsappLink::url($this->whatsapp);
    }
}
