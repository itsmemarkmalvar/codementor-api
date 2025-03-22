<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\AI\TutorService;
use App\Services\AI\JavaExecutionService;

class AIServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TutorService::class, function ($app) {
            return new TutorService();
        });

        $this->app->singleton(JavaExecutionService::class, function ($app) {
            return new JavaExecutionService();
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