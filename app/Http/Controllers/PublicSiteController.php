<?php

namespace App\Http\Controllers;

use App\Models\ActorType;
use App\Models\Network;
use App\Models\Organization;
use App\Models\Party;
use App\Models\PublicContent;
use App\Support\Tenancy\NetworkContext;

class PublicSiteController extends Controller
{
    public const HOME_LIMIT = 3;

    public function home(string $networkSlug)
    {
        $network = $this->resolveNetwork($networkSlug);

        $carousel = $this->publishedContent('carousel')->orderBy('sort_order')->get();
        $organizations = $this->directoryOrganizations()->limit(self::HOME_LIMIT)->get();
        $specialists = $this->directorySpecialists()->limit(self::HOME_LIMIT)->get();
        $posts = $this->publishedContent('blog')->orderByDesc('published_at')->limit(self::HOME_LIMIT)->get();
        $pages = $this->publishedContent('page')->orderBy('sort_order')->get();

        $organizationsTotal = $this->directoryOrganizations()->count();
        $specialistsTotal = $this->directorySpecialists()->count();
        $postsTotal = $this->publishedContent('blog')->count();

        return view('public.home', compact(
            'network',
            'carousel',
            'organizations',
            'specialists',
            'posts',
            'pages',
            'organizationsTotal',
            'specialistsTotal',
            'postsTotal',
        ));
    }

    public function organizations(string $networkSlug)
    {
        $network = $this->resolveNetwork($networkSlug);
        $organizations = $this->directoryOrganizations()->get();

        return view('public.organizations', compact('network', 'organizations'));
    }

    public function specialists(string $networkSlug)
    {
        $network = $this->resolveNetwork($networkSlug);
        $specialists = $this->directorySpecialists()->get();

        return view('public.specialists', compact('network', 'specialists'));
    }

    public function posts(string $networkSlug)
    {
        $network = $this->resolveNetwork($networkSlug);
        $posts = $this->publishedContent('blog')->orderByDesc('published_at')->get();

        return view('public.posts', compact('network', 'posts'));
    }

    public function organization(string $networkSlug, string $organizationSlug)
    {
        $network = $this->resolveNetwork($networkSlug);

        $organization = Organization::query()
            ->where('slug', $organizationSlug)
            ->where('show_in_directory', true)
            ->where('is_active', true)
            ->firstOrFail();

        $specialists = Party::query()
            ->where('organization_id', $organization->id)
            ->where('is_active', true)
            ->whereHas('actorType', fn ($query) => $query->where('show_in_directory', true))
            ->orderBy('display_name')
            ->get();

        return view('public.organization', compact('network', 'organization', 'specialists'));
    }

    public function specialist(string $networkSlug, int $party)
    {
        $network = $this->resolveNetwork($networkSlug);

        $model = Party::query()
            ->with(['organization', 'actorType'])
            ->findOrFail($party);

        abort_unless($model->is_active && $model->actorType?->show_in_directory, 404);

        return view('public.specialist', ['network' => $network, 'party' => $model]);
    }

    public function tutorials()
    {
        return redirect()->route('filament.admin.pages.capacitacion');
    }

    public function post(string $networkSlug, string $slug)
    {
        $network = $this->resolveNetwork($networkSlug);

        $post = $this->publishedContent('blog')
            ->where('slug', $slug)
            ->firstOrFail();

        return view('public.post', compact('network', 'post'));
    }

    public function page(string $networkSlug, string $slug)
    {
        $network = $this->resolveNetwork($networkSlug);

        $page = $this->publishedContent('page')
            ->where('slug', $slug)
            ->firstOrFail();

        return view('public.page', compact('network', 'page'));
    }

    public function help(string $networkSlug)
    {
        $network = $this->resolveNetwork($networkSlug);

        $pages = $this->publishedContent('page')->orderBy('sort_order')->get();
        $help = $pages->firstWhere('slug', 'ayuda') ?? $pages->first();

        return view('public.help', compact('network', 'pages', 'help'));
    }

    protected function publishedContent(string $type)
    {
        return PublicContent::query()
            ->where('type', $type)
            ->where('is_published', true);
    }

    protected function directoryOrganizations()
    {
        return Organization::query()
            ->where('show_in_directory', true)
            ->where('is_active', true)
            ->orderBy('name');
    }

    protected function directorySpecialists()
    {
        $specialistTypeIds = ActorType::query()
            ->where('show_in_directory', true)
            ->pluck('id');

        return Party::query()
            ->with('organization')
            ->whereIn('actor_type_id', $specialistTypeIds)
            ->where('is_active', true)
            ->orderBy('display_name');
    }

    protected function resolveNetwork(string $slug): Network
    {
        $network = Network::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        NetworkContext::set($network);

        return $network;
    }
}
