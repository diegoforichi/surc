<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToNetwork;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class PublicContent extends Model
{
    use BelongsToNetwork;

    protected $table = 'public_content';

    protected $fillable = [
        'network_id',
        'type',
        'title',
        'slug',
        'body',
        'excerpt',
        'seo_description',
        'image_path',
        'is_published',
        'published_at',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    protected function slug(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value): ?string => filled($value) ? $value : null,
        );
    }
}
