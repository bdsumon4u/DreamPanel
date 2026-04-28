<?php

use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/get-service-id/{domain}', function (string $domain) {
    $site = Site::query()
        ->where('domain', $domain)
        ->first(['id', 'service_id']);

    if ($site) {
        return response()->json([
            'service_id' => $site->service_id,
        ]);
    }

    return response()->json([
        'message' => 'Site not found',
    ], 404);
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
