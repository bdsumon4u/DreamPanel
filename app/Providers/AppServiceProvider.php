<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;

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
        Model::unguard();

        // Purge database connections after each job is processed or fails
        // to prevent connection pool exhaustion during batch job execution
        Queue::after(function (JobProcessed $event): void {
            DB::purge();
        });

        Queue::failing(function (JobFailed $event): void {
            DB::purge();
        });
    }
}
