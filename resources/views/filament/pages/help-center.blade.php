<x-filament-panels::page>
    <div class="grid gap-6 lg:grid-cols-4">
        <aside class="space-y-6 lg:col-span-1">
            @forelse ($this->groupedArticles as $category => $articles)
                <div>
                    <h2 class="mb-2 text-sm font-semibold text-gray-500 dark:text-gray-400">
                        {{ \App\Models\HelpArticle::categoryLabels()[$category] ?? $category }}
                    </h2>
                    <ul class="space-y-1">
                        @foreach ($articles as $article)
                            <li>
                                <button
                                    type="button"
                                    wire:click="selectArticle({{ $article->id }})"
                                    @class([
                                        'w-full rounded px-3 py-2 text-left text-sm',
                                        'bg-primary-500/10 font-medium text-primary-700 dark:text-primary-300' => $this->selectedArticle?->id === $article->id,
                                        'text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-800' => $this->selectedArticle?->id !== $article->id,
                                    ])
                                >
                                    {{ $article->title }}
                                </button>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @empty
                <p class="text-sm text-gray-500">Todavía no hay artículos publicados para su perfil.</p>
            @endforelse
        </aside>

        <article class="space-y-4 lg:col-span-3">
            @if ($this->selectedArticle)
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <h2 class="text-2xl font-semibold">{{ $this->selectedArticle->title }}</h2>
                    <a
                        href="{{ route('help.articles.pdf', $this->selectedArticle) }}"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex items-center rounded-lg bg-primary-600 px-3 py-2 text-sm font-medium text-white hover:bg-primary-500"
                    >
                        Descargar guía PDF
                    </a>
                </div>
                @if ($this->selectedArticle->excerpt)
                    <p class="text-gray-600 dark:text-gray-300">{{ $this->selectedArticle->excerpt }}</p>
                @endif
                <x-video-embed :url="$this->selectedArticle->video_url" />
                <div class="prose dark:prose-invert max-w-none">
                    {!! \App\Support\Html\SafeHtml::render($this->selectedArticle->body) !!}
                </div>
            @else
                <p class="text-gray-600">Seleccione un artículo del menú para leer la guía de su rol.</p>
            @endif
        </article>
    </div>
</x-filament-panels::page>
