<?php

use App\Http\Controllers\Api\RecipeController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

Route::post('sanctum/token', function (Request $request) {
    $request->validate([
        'email' => ['required', 'email'],
        'password' => ['required'],
        'device_name' => ['required'],
    ]);

    $user = User::firstWhere('email', $request->email);

    if (! $user || ! Hash::check($request->password, $user->password)) {
        throw ValidationException::withMessages([
            'email' => ['The provided credentials are incorrect.'],
        ]);
    }

    return response()->json([
        ...$user->toArray(),
        'token' => $user->createToken($request->device_name)->plainTextToken,
    ]);
});

Route::middleware('auth:sanctum')
    ->name('api.')
    ->group(function (): void {
        Route::get('user', fn (Request $request) => $request->user())->name('user');

        Route::get('recipes', [RecipeController::class, 'index'])->name('recipes.index');
        Route::post('recipes', [RecipeController::class, 'store'])->name('recipes.store');
        Route::get('recipes/{recipe}', [RecipeController::class, 'show'])->name('recipes.show');
        Route::patch('recipes/{recipe}', [RecipeController::class, 'update'])->name('recipes.update');
        Route::delete('recipes/{recipe}', [RecipeController::class, 'destroy'])->name('recipes.destroy');
    });
