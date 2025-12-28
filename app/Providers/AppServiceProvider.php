<?php

namespace App\Providers;

use App\Models\Peminjaman;
use App\Models\Pengembalian;
use App\Models\User;
use App\Models\Radio;
use App\Observers\PeminjamanObserver;
use App\Observers\PengembalianObserver;
use App\Observers\UserObserver;
use App\Observers\RadioObserver;
use Filament\Support\Facades\FilamentView;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
        parent::register();
        FilamentView::registerRenderHook('panels::body.end', fn(): string => Blade::render("@vite('resources/js/app.js')"));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
        Gate::define('viewApiDocs', function (User $user) {
            return true;
        });
        // Gate::policy()
        Event::listen(function (\SocialiteProviders\Manager\SocialiteWasCalled $event) {
            $event->extendSocialite('discord', \SocialiteProviders\Google\Provider::class);
        });

        // Use container to resolve observers with dependencies
        Peminjaman::observe(app(PeminjamanObserver::class));
        Pengembalian::observe(app(PengembalianObserver::class));
        User::observe(app(UserObserver::class));
        Radio::observe(app(RadioObserver::class));
    }
}
