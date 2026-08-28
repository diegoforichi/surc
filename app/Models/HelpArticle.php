<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class HelpArticle extends Model
{
    public const CATEGORY_START = 'primeros_pasos';

    public const CATEGORY_OPERATION = 'operacion';

    public const CATEGORY_ADMIN = 'administracion';

    public const CATEGORY_SPECIALIST = 'especialista';

    public const CATEGORY_VIDEOS = 'videos';

    protected $fillable = [
        'title',
        'slug',
        'category',
        'body',
        'excerpt',
        'video_url',
        'audience_roles',
        'sort_order',
        'is_published',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'audience_roles' => 'array',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function categoryLabels(): array
    {
        return [
            self::CATEGORY_START => 'Primeros pasos',
            self::CATEGORY_OPERATION => 'Operación',
            self::CATEGORY_ADMIN => 'Administración',
            self::CATEGORY_SPECIALIST => 'Especialista',
            self::CATEGORY_VIDEOS => 'Videos',
        ];
    }

    public function categoryLabel(): string
    {
        return self::categoryLabels()[$this->category] ?? $this->category;
    }

    public function isVisibleTo(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        $roles = $this->audience_roles ?? [];

        if ($roles === []) {
            return true;
        }

        return $user->getRoleNames()->intersect($roles)->isNotEmpty();
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }
}
