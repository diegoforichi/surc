<?php

namespace App\Http\Middleware;

use App\Models\Network;
use App\Support\Tenancy\NetworkContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetNetworkContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $network = null;

        if ($request->route('networkSlug')) {
            $network = Network::query()
                ->where('slug', $request->route('networkSlug'))
                ->where('is_active', true)
                ->first();
        } elseif ($user = $request->user()) {
            $network = $user->network;
        }

        NetworkContext::set($network);

        return $next($request);
    }
}
