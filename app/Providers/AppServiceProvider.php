<?php

namespace App\Providers;

use App\Enums\RoleEnum;
use App\Models\User;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Pulse\Facades\Pulse;

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
        Gate::define('viewApiDocs', function (User $user): bool {
            return in_array($user->role, [RoleEnum::SUPER_ADMIN, RoleEnum::ADMIN], true);
        });

        Gate::define('viewPulse', function (User $user): bool {
            return in_array($user->role, [RoleEnum::SUPER_ADMIN, RoleEnum::ADMIN], true);
        });

        Pulse::user(fn (User $user): array => [
            'name' => trim("{$user->first_name} {$user->last_name}") ?: $user->phone,
            'extra' => $user->phone,
        ]);

        Scramble::configure()
            ->withDocumentTransformers(function (OpenApi $openApi) {
                $openApi->secure(
                    SecurityScheme::http('bearer')
                );
            });
    }
}
