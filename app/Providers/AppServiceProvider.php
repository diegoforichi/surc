<?php

namespace App\Providers;

use App\Domain\Templates\IndustryTemplateRegistry;
use App\Models\CaseRecord;
use App\Models\User;
use App\Observers\CaseRecordObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(IndustryTemplateRegistry::class);
    }

    public function boot(): void
    {
        CaseRecord::observe(CaseRecordObserver::class);

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip());
        });

        Gate::before(function (User $user, string $ability): ?bool {
            if (str_starts_with($ability, 'history.')) {
                return null;
            }

            if ($user->is_platform_owner) {
                return true;
            }

            return null;
        });
    }
}
