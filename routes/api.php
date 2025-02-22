<?php

use App\Http\Controllers\Api\EventController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('user/status', function (Request $request) {
    return collect($request->user()->status_list)
        ->map(fn ($item) => $item['emoji'] . ' - ' . $item['text']);
})->middleware('auth:sanctum');

Route::post('user/status', function (Request $request) {
    $data = $request->validate([
        'status' => 'required',
    ]);

    $request->user()->update(['status' => $data['status']]);
})->middleware('auth:sanctum');

Route::delete('user/status', function (Request $request) {
    $request->user()->update(['status' => null]);
})->middleware('auth:sanctum');

Route::get('events', EventController::class);
