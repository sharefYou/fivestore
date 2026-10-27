<?php

namespace App\Providers;

use App\Services\SiteSettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One cached settings row per request, shared by the layout and pages.
        $this->app->singleton(SiteSettings::class);

        // Livewire writes in-progress uploads to the *default* filesystem
        // disk, and this application points that at `public` so catalog
        // images can be served. That combination would publish every
        // half-finished upload under /storage before any validation has
        // accepted it, so temporary files are pinned to a private disk
        // unless a deployment names one deliberately.
        if (blank(config('livewire.temporary_file_upload.disk'))) {
            config(['livewire.temporary_file_upload.disk' => 'local']);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Outside production, surface lazy loading and silently ignored
        // attributes as errors instead of letting them reach a page as an
        // N+1 query or a quietly dropped field.
        Model::shouldBeStrict(! $this->app->isProduction());

        // Administrator passwords are the only credentials in this system.
        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(12)->letters()->mixedCase()->numbers()->symbols()->uncompromised()
            : Password::min(8)->letters()->numbers());
    }
}
