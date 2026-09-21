<?php

use App\NativeComponents\Home;
use App\NativeComponents\Login;
use App\NativeComponents\Register;
use Illuminate\Support\Facades\Route;

Route::native('/', Home::class);
Route::native('/login', Login::class);
Route::native('/register', Register::class);
