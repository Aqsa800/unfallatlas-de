<?php

use App\Http\Controllers\Api\AccidentController;
use App\Http\Controllers\Api\AggregateController;
use App\Http\Controllers\Api\MetaController;
use App\Http\Controllers\Api\RegionController;
use App\Http\Controllers\Api\StatsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — German Road Accidents (DMProject)
|--------------------------------------------------------------------------
|
| All routes are prefixed with /api/v1 by the group below.
| The ForceJsonResponse middleware guarantees every response carries the
| correct Content-Type header and that Laravel exceptions are serialised
| as JSON rather than HTML for API consumers.
|
*/

Route::prefix('v1')
    ->middleware(['force.json'])
    ->group(function () {

        // ----------------------------------------------------------------
        // Regions
        // ----------------------------------------------------------------
        Route::get('regions',      [RegionController::class, 'index']);
        Route::get('regions/{id}', [RegionController::class, 'show']);

        // ----------------------------------------------------------------
        // Accidents (individual records)
        // ----------------------------------------------------------------
        Route::get('accidents', [AccidentController::class, 'index']);

        // ----------------------------------------------------------------
        // Aggregates
        // ----------------------------------------------------------------
        Route::prefix('aggregates')->group(function () {
            Route::get('accidents',      [AggregateController::class, 'accidents']);
            Route::get('time-series',    [AggregateController::class, 'timeSeries']);
            Route::get('rates',          [AggregateController::class, 'rates']);
            Route::get('rankings',       [AggregateController::class, 'rankings']);
            Route::get('year-over-year', [AggregateController::class, 'yearOverYear']);
            Route::get('earliest-year',  [AggregateController::class, 'earliestYear']);
            Route::get('zero-cases',     [AggregateController::class, 'zeroCases']);
        });

        // ----------------------------------------------------------------
        // Metadata & import history
        // ----------------------------------------------------------------
        Route::get('metadata/sources', [MetaController::class, 'sources']);
        Route::get('import-runs',      [MetaController::class, 'importRuns']);

        // ----------------------------------------------------------------
        // Summary stats
        // ----------------------------------------------------------------
        Route::get('stats/summary', [StatsController::class, 'summary']);
    });
