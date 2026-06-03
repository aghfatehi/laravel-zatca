<?php

use Aghfatehi\Zatca\Facades\Zatca;
use Illuminate\Support\Facades\Route;

Route::middleware(config('zatca.api_middleware', 'api'))->prefix('zatca')->group(function () {
    Route::post('onboard', function () {
        // Onboarding API endpoint
    });

    Route::post('invoice/sync', function () {
        // Invoice sync API endpoint
    });

    Route::get('status', function () {
        return response()->json([
            'phase' => Zatca::phase()->value,
            'environment' => Zatca::environment()->value,
            'phase1_enabled' => Zatca::isPhase1Enabled(),
            'phase2_enabled' => Zatca::isPhase2Enabled(),
        ]);
    });
});
