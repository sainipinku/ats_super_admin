<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Member\JobController;
use App\Http\Controllers\Api\Member\ProfileController;
use App\Http\Controllers\Api\Member\TaskController;
use Illuminate\Support\Facades\Route;

    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/send-otp', [AuthController::class, 'sendOtp']);
        Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);

        Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);

        Route::get('/profile', [ProfileController::class, 'show']);
        Route::put('/profile', [ProfileController::class, 'update']);
        Route::get('/profile/completion', [ProfileController::class, 'completion']);

        Route::get('/jobs', [JobController::class, 'index']);
        Route::get('/jobs/{job}', [JobController::class, 'show']);
        Route::post('/jobs/{job}/apply', [JobController::class, 'apply']);
        Route::get('/applications', [JobController::class, 'myApplications']);
        Route::delete('/applications/{application}', [JobController::class, 'withdraw']);

        Route::get('/tasks', [TaskController::class, 'index']);
        Route::get('/tasks/{task}', [TaskController::class, 'show']);
        Route::get('/tasks/{task}/notes', [TaskController::class, 'notesIndex']);
        Route::post('/tasks/{task}/notes', [TaskController::class, 'notesStore']);
        Route::delete('/notes/{note}', [TaskController::class, 'notesDestroy']);
    });
