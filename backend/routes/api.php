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
use App\Http\Controllers\TournamentPlayerController;
use App\Http\Controllers\PlayerStatsController;
use App\Http\Controllers\StandingsController;
use App\Http\Controllers\TournamentRosterController;
use App\Http\Controllers\TournamentTeamController;
use App\Http\Controllers\BoxscoreController;
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
    Route::post('/players/import/preview', [PlayerController::class, 'previewImport']);
    Route::apiResource('/players', PlayerController::class);

    // TOURNAMENT
    Route::get('/tournaments/template', [TournamentController::class, 'template']);
    Route::post('/tournaments/import', [TournamentController::class, 'import']);
    Route::apiResource('/tournaments', TournamentController::class);

    // TOURNAMENT TEAM
    Route::get('/tournaments/{tournament}/teams', [TournamentTeamController::class, 'index']);
    Route::get('/tournaments/{tournament}/teams/available', [TournamentTeamController::class, 'available']);
    Route::post('/tournaments/{tournament}/teams', [TournamentTeamController::class, 'store']);
    Route::put('/tournaments/{tournament}/teams', [TournamentTeamController::class, 'sync']);
    Route::delete('/tournaments/{tournament}/teams/{team}', [TournamentTeamController::class, 'destroy']);
    Route::get('/tournaments/{tournament}/teams/{team}/players', [TournamentRosterController::class, 'index']);
    Route::post('/tournaments/{tournament}/teams/{team}/players', [TournamentRosterController::class, 'store']);
    Route::post('/tournaments/{tournament}/teams/{team}/players/import', [TournamentRosterController::class, 'import']);
    Route::post('/tournaments/{tournament}/teams/{team}/players/import/preview', [TournamentRosterController::class, 'previewImport']);
    Route::delete('/tournaments/{tournament}/teams/{team}/players/{player}', [TournamentRosterController::class, 'destroy'])->whereNumber('player');
    Route::get('/tournaments/{tournament}/players', [TournamentPlayerController::class, 'index']);
    Route::get('/tournaments/{tournament}/players/export/xlsx', [TournamentPlayerController::class, 'exportXlsx']);
    Route::get('/tournaments/{tournament}/players/export/pdf', [TournamentPlayerController::class, 'exportPdf']);

    // STANDINGS
    Route::get('/tournaments/{tournament}/standings', [StandingsController::class, 'index']);
    Route::get('/tournaments/{tournament}/standings/export/{format}', [StandingsController::class, 'export'])->whereIn('format', ['xlsx', 'pdf']);

    // PLAYER STATS
    Route::get('/tournaments/{tournament}/stats/batting', [PlayerStatsController::class, 'index']);
    Route::get('/tournaments/{tournament}/stats/batting/export/{format}', [PlayerStatsController::class, 'export'])->whereIn('format', ['xlsx', 'pdf']);

    // GAME
    Route::get('/tournaments/{tournament}/games', [GameController::class, 'index']);
    Route::get('/tournaments/{tournament}/games/export/xlsx', [GameController::class, 'exportXlsx']);
    Route::get('/tournaments/{tournament}/games/export/pdf', [GameController::class, 'exportPdf']);
    Route::post('/tournaments/{tournament}/games/preview', [GameController::class, 'preview']);
    Route::post('/tournaments/{tournament}/games/generate', [GameController::class, 'generate']);
    Route::put('/tournaments/{tournament}/games/{game}', [GameController::class, 'update']);
    Route::get('/tournaments/{tournament}/games/{game}/boxscore', [BoxscoreController::class, 'show']);
    Route::put('/tournaments/{tournament}/games/{game}/boxscore', [BoxscoreController::class, 'update']);
    Route::delete('/tournaments/{tournament}/games', [GameController::class, 'destroy']);

     // CONTENT TYPE
    Route::apiResource('/content-type', ContentTypeController::class)->only(['index']);
});
