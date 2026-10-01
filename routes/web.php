<?php

use App\Http\Controllers\SessionController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Map');

Route::middleware('guest')->group(function () {
    Route::inertia('/login', 'Login')->name('login');
    Route::post('/login', [SessionController::class, 'login']);
    Route::inertia('/register', 'Register');
    Route::post('/register', [SessionController::class, 'register']);
});

Route::middleware('auth')->group(function () {
    Route::inertia('/collections', 'Collections');
    Route::post('/logout', [SessionController::class, 'logout']);
});
