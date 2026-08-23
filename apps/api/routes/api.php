<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Presentation\Api\Controllers\MunicipalityAmountController;
use Presentation\Api\Controllers\SnapshotMetaController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// 市区町村金額API
Route::get('/muni/amounts', [MunicipalityAmountController::class, 'index'])
    ->name('api.muni.amounts.index');

// スナップショットメタAPI
Route::get('/muni/snapshot-meta', [SnapshotMetaController::class, 'show'])
    ->name('api.muni.snapshot-meta.show');
