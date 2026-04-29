<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\Management;
use App\Http\Controllers\Public;
use App\Http\Middleware\AdminMiddleware;
use Illuminate\Support\Facades\Route;

Route::post('login', [AuthController::class, 'login']);
Route::post('register', [AuthController::class, 'register']);

Route::prefix('management')->group(function () {
    Route::middleware('auth:sanctum')->group(function () {
        Route::apiSingleton('profile', Management\ProfileController::class)->only(['show']);
        Route::apiResource('films', Management\FilmController::class)->except(['show']);
        Route::apiResource('films.persons', Management\FilmPersonController::class)->except(['show']);
        Route::apiResource('film-watchers', Management\FilmWatcherController::class)->except(['show']);
        Route::get('film-watchers/by-film/{film}', [Management\FilmWatcherController::class, 'byFilm']);
        Route::apiResource('people', Management\PersonController::class)->except(['show']);
        Route::apiResource('companies', Management\CompanyController::class)->except(['show']);

        Route::get('genres', [Management\GenreController::class, 'index']);
        Route::get('countries', [Management\CountryController::class, 'index']);
        Route::get('tags', [Management\TagController::class, 'index']);

        Route::middleware(AdminMiddleware::class)
            ->apiResource('genres', Management\GenreController::class)
            ->except(['show', 'index']);

        Route::middleware(AdminMiddleware::class)
            ->apiResource('countries', Management\CountryController::class)
            ->except(['show', 'index']);

        Route::middleware(AdminMiddleware::class)
            ->apiResource('tags', Management\TagController::class)
            ->except(['show', 'index']);
    });

    Route::apiResource('films', Management\FilmController::class)->only(['show']);
    Route::apiResource('people', Management\PersonController::class)->only(['show']);
    Route::apiResource('companies', Management\CompanyController::class)->only(['show']);
});

Route::apiResource('films', Public\FilmController::class)->only(['index']);
Route::get('genre/{genre:slug}', [Public\GenreController::class, 'show']);

Route::get('sitemap', [Public\SitemapController::class, 'index']);

Route::prefix('films/{film}/feedback')->group(function () {
    Route::get('', [FeedbackController::class, 'index']);
    Route::post('', [FeedbackController::class, 'store'])->middleware('auth:sanctum');
    Route::put('{feedback}', [FeedbackController::class, 'update'])->middleware('auth:sanctum');
});
