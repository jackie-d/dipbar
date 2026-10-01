<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\NoteCollectionController;
use App\Http\Controllers\NoteController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Readable by guests (public notes only) or with a token (own + collection notes too).
Route::get('/notes', [NoteController::class, 'index']);
Route::get('/notes/{note}', [NoteController::class, 'show'])->whereNumber('note');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn (Request $request) => $request->user());
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/notes/mine', [NoteController::class, 'mine']);
    Route::apiResource('notes', NoteController::class)->only(['store', 'update', 'destroy']);

    Route::apiResource('collections', NoteCollectionController::class);
    Route::post('/collections/{collection}/members', [NoteCollectionController::class, 'addMember']);
    Route::delete('/collections/{collection}/members/{user}', [NoteCollectionController::class, 'removeMember']);
});
