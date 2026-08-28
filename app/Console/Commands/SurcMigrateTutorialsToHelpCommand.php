<?php

namespace App\Console\Commands;

use App\Models\HelpArticle;
use App\Models\PublicContent;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SurcMigrateTutorialsToHelpCommand extends Command
{
    protected $signature = 'surc:migrate-tutorials-to-help';

    protected $description = 'Pasa tutoriales públicos por red a borradores globales de capacitación';

    public function handle(): int
    {
        $tutorials = PublicContent::query()
            ->withoutGlobalScopes()
            ->where('type', 'tutorial')
            ->get();

        if ($tutorials->isEmpty()) {
            $this->info('No hay tutoriales para migrar.');

            return self::SUCCESS;
        }

        $migrated = 0;

        foreach ($tutorials as $tutorial) {
            $slug = 'migrado-tutorial-'.$tutorial->id;
            $baseSlug = Str::slug((string) $tutorial->slug ?: $tutorial->title) ?: $slug;

            if (! HelpArticle::query()->where('slug', $baseSlug)->exists()) {
                $slug = $baseSlug;
            }

            HelpArticle::query()->firstOrCreate(
                ['slug' => $slug],
                [
                    'title' => $tutorial->title,
                    'category' => HelpArticle::CATEGORY_VIDEOS,
                    'body' => $tutorial->body,
                    'excerpt' => $tutorial->excerpt,
                    'audience_roles' => [],
                    'sort_order' => 200 + $tutorial->id,
                    'is_published' => false,
                    'published_at' => null,
                ],
            );

            $migrated++;
        }

        $this->info("Tutoriales migrados como borradores: {$migrated}. Revíselos en Plataforma → Capacitación.");

        return self::SUCCESS;
    }
}
