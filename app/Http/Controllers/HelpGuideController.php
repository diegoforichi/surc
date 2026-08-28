<?php

namespace App\Http\Controllers;

use App\Models\HelpArticle;
use App\Support\Help\HelpContentResolver;
use App\Support\Html\SafeHtml;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class HelpGuideController extends Controller
{
    public function pdf(HelpArticle $article): Response
    {
        abort_unless($article->is_published, 404);
        abort_unless($article->isVisibleTo(Auth::user()), 403);

        $resolved = HelpContentResolver::hydrate($article);

        $pdf = Pdf::loadView('help.article', [
            'title' => $resolved->title,
            'excerpt' => $resolved->excerpt,
            'body' => SafeHtml::render($resolved->body),
            'networkName' => Auth::user()?->network?->name ?? 'SURC',
            'issuedBy' => Auth::user()?->name ?? '—',
            'issuedAt' => now(),
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('guia-'.$article->slug.'.pdf');
    }
}
