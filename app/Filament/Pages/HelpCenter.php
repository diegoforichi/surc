<?php

namespace App\Filament\Pages;

use App\Models\HelpArticle;
use App\Support\Help\HelpContentResolver;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class HelpCenter extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationGroup = 'Ayuda';

    protected static ?string $navigationLabel = 'Capacitación';

    protected static ?string $title = 'Capacitación';

    protected static ?string $slug = 'capacitacion';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.help-center';

    public ?int $selectedArticleId = null;

    public static function canAccess(): bool
    {
        return Auth::check();
    }

    public function selectArticle(int $id): void
    {
        $this->selectedArticleId = $id;
    }

    public function getSelectedArticleProperty(): ?HelpArticle
    {
        if ($this->selectedArticleId === null) {
            return $this->visibleArticles()->first();
        }

        return $this->visibleArticles()->firstWhere('id', $this->selectedArticleId);
    }

    /**
     * @return Collection<string, Collection<int, HelpArticle>>
     */
    public function getGroupedArticlesProperty(): Collection
    {
        return $this->visibleArticles()->groupBy('category');
    }

    /**
     * @return Collection<int, HelpArticle>
     */
    protected function visibleArticles(): Collection
    {
        $user = Auth::user();

        return HelpArticle::query()
            ->published()
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get()
            ->filter(fn (HelpArticle $article): bool => $article->isVisibleTo($user))
            ->map(fn (HelpArticle $article): HelpArticle => HelpContentResolver::hydrate($article))
            ->values();
    }
}
