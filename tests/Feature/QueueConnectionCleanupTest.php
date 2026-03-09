<?php

use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Jobs\TestConnectionJob;
use Tests\Jobs\TestFailingJob;

test('database connection is released after queue job finishes', function () {
    DB::purge();

    // Dispatch the job on sync queue (executes immediately and fires events)
    dispatch(new TestConnectionJob)->onConnection('sync');

    // Verify the connection was purged after job completion
    expect(DB::getConnections())->toBeEmpty();
});

test('database connection is released after queue job fails', function () {
    DB::purge();

    // Dispatch the failing job on sync queue
    try {
        dispatch(new TestFailingJob)->onConnection('sync');
    } catch (\Exception $e) {
        // Expected exception
    }

    // Verify the connection was purged after job failure
    expect(DB::getConnections())->toBeEmpty();
});

test('DB disconnect listener is registered for JobProcessed event', function () {
    // Verify that our listener is actually registered
    $listeners = Event::getListeners(JobProcessed::class);

    expect($listeners)->not->toBeEmpty();
});
