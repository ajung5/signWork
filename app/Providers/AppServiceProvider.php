<?php

namespace App\Providers;

use App\Services\BsreClient;
use App\Services\BsreSigningProvider;
use App\Services\MockSigningProvider;
use App\Services\SigningProvider;
use Illuminate\Support\ServiceProvider;
use LogicException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(BsreClient::class, fn (): BsreClient => new BsreClient(
            (array) config('signwork.bsre', [])
        ));
        $this->app->bind(SigningProvider::class, function ($app): SigningProvider {
            return match (config('signwork.provider')) {
                'mock' => $app->make(MockSigningProvider::class),
                'bsre' => $app->make(BsreSigningProvider::class),
                default => throw new LogicException('SIGNWORK_PROVIDER tidak dikenali.'),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
