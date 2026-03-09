<?php

namespace Tests\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class TestFailingJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        // Simulate a DB operation that creates a connection
        DB::connection();

        throw new \Exception('Intentional failure');
    }
}
