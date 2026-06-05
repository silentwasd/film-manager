<?php

use App\Http\Controllers\KotonetAuthController;
use Illuminate\Support\Facades\Route;

Route::get('auth/kotonet', [KotonetAuthController::class, 'redirect']);
Route::get('auth/callback', [KotonetAuthController::class, 'callback']);
