<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\NexusFormController;

Route::get('/', function () {
    // Quand on arrive sur la racine, on va vers la landing Nexus
    return redirect()->route('nexus.landing');
});

Route::prefix('nexus')->group(function () {
    Route::get('/landing', [NexusFormController::class, 'showLanding'])
        ->name('nexus.landing');

    Route::get('/', [NexusFormController::class, 'showForm'])
        ->name('nexus.form');

    Route::middleware('throttle:30,1')->group(function () {
        Route::post('/intention', [NexusFormController::class, 'analyzeIntention'])
            ->name('nexus.intention');

        Route::post('/submit', [NexusFormController::class, 'submit'])
            ->name('nexus.submit');
    });
});