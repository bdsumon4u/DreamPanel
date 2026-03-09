<?php

namespace Tests\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class TestConnectionJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        // Simulate a DB operation that creates a connection
        DB::connection();
    }
}
