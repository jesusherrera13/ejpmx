<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\ContentTypeController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\TournamentController;
use App\Http\Controllers\TournamentTeamController;
use App\Http\Controllers\GameController;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

Route::group(['middleware' => ['auth:sanctum']], function() {

    // AUTH
    Route::post('/logout', [AuthController::class, 'logout']);

    // USER
    Route::apiResource('/user', UserController::class);

    // ROLE
    Route::apiResource('/role', RoleController::class)->only(['index']);

    // BLOG
    Route::apiResource('/blog', BlogController::class);

    // TEAM
    Route::get('/teams/template', [TeamController::class, 'template']);
    Route::post('/teams/import', [TeamController::class, 'import']);
    Route::apiResource('/teams', TeamController::class);

    // PLAYER
    Route::get('/players/template', [PlayerController::class, 'template']);
    Route::post('/players/import', [PlayerController::class, 'import']);
    Route::apiResource('/players', PlayerController::class);

    // TOURNAMENT
    Route::get('/tournaments/template', [TournamentController::class, 'template']);
    Route::post('/tournaments/import', [TournamentController::class, 'import']);
    Route::apiResource('/tournaments', TournamentController::class);

    // TOURNAMENT TEAM
    Route::get('/tournaments/{tournament}/teams', [TournamentTeamController::class, 'index']);
    Route::post('/tournaments/{tournament}/teams', [TournamentTeamController::class, 'store']);
    Route::delete('/tournaments/{tournament}/teams/{team}', [TournamentTeamController::class, 'destroy']);

    // GAME
    Route::get('/tournaments/{tournament}/games', [GameController::class, 'index']);
    Route::post('/tournaments/{tournament}/games/generate', [GameController::class, 'generate']);
    Route::delete('/tournaments/{tournament}/games', [GameController::class, 'destroy']);

     // CONTENT TYPE
    Route::apiResource('/content-type', ContentTypeController::class)->only(['index']);
});
