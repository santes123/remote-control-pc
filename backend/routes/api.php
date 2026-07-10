<?php

use App\Http\Controllers\Api\PcControlLinkController;
use App\Http\Controllers\Api\PcStatusController;
use App\Http\Controllers\Api\PcPowerController;
use App\Http\Controllers\Api\PcWakeController;
use Illuminate\Support\Facades\Route;

Route::get('/pc/status', PcStatusController::class);
Route::get('/pc/control-link', PcControlLinkController::class);
Route::post('/pc/wake', PcWakeController::class);
Route::post('/pc/shutdown', [PcPowerController::class, 'shutdown']);
Route::post('/pc/restart', [PcPowerController::class, 'restart']);
