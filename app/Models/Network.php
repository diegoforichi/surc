<?php

namespace App\Models;

use App\Support\Contact\WhatsappLink;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Network extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'industry_template_key',
        'logo_path',
        'cover_path',
        'primary_color',
        'slogan',
        'description',
        'phone',
        'email',
        'whatsapp',
        'address',
        'settings',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function organizations(): HasMany
    {
        return $this->hasMany(Organization::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function terminology(): HasMany
    {
        return $this->hasMany(Terminology::class);
    }

    public function actorTypes(): HasMany
    {
        return $this->hasMany(ActorType::class);
    }

    public function workflowTemplates(): HasMany
    {
        return $this->hasMany(WorkflowTemplate::class);
    }

    public function customFieldDefinitions(): HasMany
    {
        return $this->hasMany(CustomFieldDefinition::class);
    }

    public function historyEntryTypes(): HasMany
    {
        return $this->hasMany(HistoryEntryType::class);
    }

    public function whatsappUrl(): ?string
    {
        return WhatsappLink::url($this->whatsapp);
    }

    public function seoDescription(): ?string
    {
        $text = filled($this->slogan) ? (string) $this->slogan : (string) $this->description;

        if (! filled($text)) {
            return null;
        }

        return Str::limit(strip_tags($text), 160, '');
    }
}
