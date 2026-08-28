<?php

namespace App\Support\Help;

use App\Models\HelpArticle;
use App\Models\HistoryEntryType;
use App\Support\Tenancy\NetworkContext;

class HelpContentResolver
{
    /**
     * @var array<string, array{0: string, 1: string}>
     */
    private const SINGULAR_TOKENS = [
        'organization' => ['organization', 'sede'],
        'subject' => ['subject', 'sujeto'],
        'client' => ['client', 'cliente'],
        'case' => ['case', 'caso'],
        'history' => ['history', 'historial'],
        'history_entry' => ['history_entry', 'registro'],
    ];

    /**
     * @var array<string, array{0: string, 1: string}>
     */
    private const PLURAL_TOKENS = [
        'organization_plural' => ['organization', 'sedes'],
        'subject_plural' => ['subject', 'sujetos'],
        'case_plural' => ['case', 'casos'],
    ];

    public static function hydrate(HelpArticle $article): HelpArticle
    {
        $article->title = self::resolve((string) $article->title);
        $article->excerpt = self::resolve((string) ($article->excerpt ?? ''));
        $article->body = self::resolve((string) ($article->body ?? ''));

        return $article;
    }

    public static function resolve(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        return strtr($text, self::replacements());
    }

    /**
     * @return array<string, string>
     */
    public static function replacements(): array
    {
        $replacements = [];

        foreach (self::SINGULAR_TOKENS as $token => [$key, $fallback]) {
            $replacements['{{'.$token.'}}'] = e(terminology($key, $fallback));
        }

        foreach (self::PLURAL_TOKENS as $token => [$key, $fallback]) {
            $replacements['{{'.$token.'}}'] = e(terminology_plural($key, $fallback));
        }

        $replacements['{{history_types}}'] = e(self::historyTypes());

        return $replacements;
    }

    protected static function historyTypes(): string
    {
        $networkId = NetworkContext::id() ?? auth()->user()?->network_id;

        if ($networkId === null) {
            return 'los tipos configurados en la red';
        }

        $labels = HistoryEntryType::query()
            ->where('network_id', $networkId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->pluck('label')
            ->filter()
            ->unique()
            ->values();

        if ($labels->isEmpty()) {
            return 'los tipos configurados en la red';
        }

        return $labels->implode(', ');
    }
}
