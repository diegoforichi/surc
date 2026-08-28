<?php

namespace App\Models;

use App\Support\Contact\WhatsappLink;
use App\Support\Tenancy\BelongsToNetwork;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Party extends Model implements HasMedia
{
    use BelongsToNetwork;
    use InteractsWithMedia;

    protected $fillable = [
        'network_id',
        'actor_type_id',
        'organization_id',
        'user_id',
        'default_workflow_template_id',
        'display_name',
        'document_id',
        'email',
        'phone',
        'whatsapp',
        'bio',
        'photo_path',
        'metadata',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function actorType(): BelongsTo
    {
        return $this->belongsTo(ActorType::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function defaultWorkflowTemplate(): BelongsTo
    {
        return $this->belongsTo(WorkflowTemplate::class, 'default_workflow_template_id');
    }

    public function ownedSubjects(): HasMany
    {
        return $this->hasMany(Subject::class, 'owner_party_id');
    }

    public function whatsappUrl(): ?string
    {
        return WhatsappLink::url($this->whatsapp);
    }
}
