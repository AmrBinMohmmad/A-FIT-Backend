<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Challenge;
use App\Models\Group;
use App\Models\Meal;
use App\Models\WeightEntry;
use App\Policies\ChallengePolicy;
use App\Policies\GroupPolicy;
use App\Policies\MealPolicy;
use App\Policies\WeightEntryPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Telescope\TelescopeServiceProvider as TelescopeBase;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Telescope is a local debugging tool only: never registered outside local, so
        // a misconfigured TELESCOPE_ENABLED cannot expose request bodies in production.
        if ($this->app->environment('local') && class_exists(TelescopeBase::class)) {
            $this->app->register(TelescopeBase::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    public function boot(): void
    {
        // Behind the TLS-terminating proxy every generated URL (photos, mail links) must be https.
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        Gate::policy(Meal::class, MealPolicy::class);
        Gate::policy(Group::class, GroupPolicy::class);
        Gate::policy(Challenge::class, ChallengePolicy::class);
        Gate::policy(WeightEntry::class, WeightEntryPolicy::class);

        $this->configureRateLimiting();
    }

    /**
     * Rate limits are the backend's first line against abuse and the knob that keeps
     * one noisy client from starving the rest. Keys are the user id when
     * authenticated, else the IP; the OTP limiter also keys on the e-mail so a single
     * address cannot be flooded from many IPs.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->getAuthIdentifier() ?? $request->ip()));

        RateLimiter::for('otp-request', fn (Request $request) => [
            Limit::perMinute(5)->by('ip:'.$request->ip()),
            Limit::perMinute(2)->by('email:'.strtolower(trim((string) $request->input('email')))),
            Limit::perDay(20)->by('email-day:'.strtolower(trim((string) $request->input('email')))),
        ]);

        RateLimiter::for('otp-verify', fn (Request $request) => [
            Limit::perMinute(10)->by('ip:'.$request->ip()),
            Limit::perMinute(6)->by('email:'.strtolower(trim((string) $request->input('email')))),
        ]);

        RateLimiter::for('steps-sync', fn (Request $request) => Limit::perMinute(12)->by('steps:'.$request->user()?->getAuthIdentifier()));

        RateLimiter::for('uploads', fn (Request $request) => Limit::perMinute(10)->by('upl:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));

        RateLimiter::for('mutations', fn (Request $request) => Limit::perMinute(60)->by('mut:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));
    }
}
