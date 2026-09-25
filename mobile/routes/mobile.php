<?php

use App\NativeComponents\CreateInventoryItem;
use App\NativeComponents\CreateRecipe;
use App\NativeComponents\Dashboard;
use App\NativeComponents\EditInventoryItem;
use App\NativeComponents\EditRecipe;
use App\NativeComponents\Home;
use App\NativeComponents\Inventory;
use App\NativeComponents\InventoryItemDetail;
use App\NativeComponents\Login;
use App\NativeComponents\RecipeDetail;
use App\NativeComponents\Recipes;
use App\NativeComponents\Register;
use App\NativeComponents\ScanInventory;
use Illuminate\Support\Facades\Route;

Route::native('/', Home::class);
Route::native('/login', Login::class);
Route::native('/register', Register::class);
Route::native('/dashboard', Dashboard::class);
Route::native('/recipes', Recipes::class);
Route::native('/recipes/create', CreateRecipe::class);
Route::native('/recipes/{id}', RecipeDetail::class);
Route::native('/recipes/{id}/edit', EditRecipe::class);
Route::native('/inventory', Inventory::class);
Route::native('/inventory/create', CreateInventoryItem::class);
Route::native('/inventory/scan', ScanInventory::class);
Route::native('/inventory/{id}', InventoryItemDetail::class);
Route::native('/inventory/{id}/edit', EditInventoryItem::class);
