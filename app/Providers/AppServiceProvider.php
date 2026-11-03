<?php

namespace App\Providers;

use App\Models\Conversation;
use App\Policies\ConversationPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDatabaseConnection();

        Paginator::useBootstrapFive();

        if (
            $this->app->environment('production')
            || parse_url((string) config('app.url'), PHP_URL_SCHEME) === 'https'
        ) {
            URL::forceScheme('https');
        }

        Gate::policy(Conversation::class, ConversationPolicy::class);
        Gate::define('view-admin-analytics', fn ($user): bool => $user->is_admin === true);
        Gate::define('view-application-logs', fn ($user): bool => $user->is_admin === true);
        Gate::define('review-crisis-events', fn ($user): bool => $user->is_admin === true);
        RateLimiter::for('login', fn (Request $request) => $this->loginLimits($request));
        RateLimiter::for('admin-login', fn (Request $request) => $this->loginLimits($request));
        RateLimiter::for('chat', fn (Request $request) => Limit::perMinute(5)
            ->by($request->user()?->getAuthIdentifier() ?? $request->ip()));
    }

    /**
     * Keep independent IP and normalized-account limits so spoofed forwarded IPs
     * cannot bypass throttling for a targeted account.
     *
     * @return array<int, Limit>
     */
    private function loginLimits(Request $request): array
    {
        $email = Str::lower(trim((string) $request->input('email')));

        return [
            Limit::perMinute(5)->by('login-ip:'.$request->ip()),
            Limit::perMinute(5)->by('login-email:'.hash('sha256', $email)),
        ];
    }

    /**
     * Auto-detect and configure active database connection if set to 'auto'.
     */
    protected function configureDatabaseConnection(): void
    {
        if (config('database.default') !== 'auto') {
            return;
        }

        if ($this->app->environment('production')) {
            config(['database.default' => 'pgsql']);
            return;
        }

        $localPort = (int) config('database.connections.mysql.port', 3306);
        $localHost = (string) config('database.connections.mysql.host', '127.0.0.1');
        $localAvailable = false;

        if (extension_loaded('pdo_mysql')) {
            $connection = @fsockopen($localHost, $localPort, $errno, $errstr, 0.15);
            if (is_resource($connection)) {
                fclose($connection);
                $localAvailable = true;
            }
        }

        if ($localAvailable) {
            config(['database.default' => 'mysql']);
        } elseif (extension_loaded('pdo_pgsql') && config('database.connections.pgsql.host')) {
            config(['database.default' => 'pgsql']);
        } else {
            config(['database.default' => 'mysql']);
        }
    }
}
