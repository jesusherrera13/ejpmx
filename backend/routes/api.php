<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\PublicBlogController;
use App\Http\Controllers\PublicAdvertisingController;
use App\Http\Controllers\PublicGameController;
use App\Http\Controllers\PublicStatsController;
use App\Http\Controllers\SponsorController;
use App\Http\Controllers\AdvertisingController;
use App\Http\Controllers\ContentTypeController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\FieldingPositionController;
use App\Http\Controllers\TournamentController;
use App\Http\Controllers\TournamentPlayerController;
use App\Http\Controllers\PlayerStatsController;
use App\Http\Controllers\StandingsController;
use App\Http\Controllers\TournamentRosterController;
use App\Http\Controllers\TournamentTeamController;
use App\Http\Controllers\TournamentTeamPdfController;
use App\Http\Controllers\BoxscoreController;
use App\Http\Controllers\BoxscorePdfController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\GlossaryPdfController;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

// Portal público: artículos y videos publicados
Route::get('/public/blogs', [PublicBlogController::class, 'index']);
Route::get('/public/blogs/{blog}', [PublicBlogController::class, 'show']);
Route::get('/public/advertising', [PublicAdvertisingController::class, 'index']);

// Portal público: calendario y resultados de los torneos públicos
Route::get('/public/tournaments', [PublicGameController::class, 'tournaments']);
Route::get('/public/tournaments/{tournament}/dates', [PublicGameController::class, 'dates']);
Route::get('/public/tournaments/{tournament}/games', [PublicGameController::class, 'games']);
Route::get('/public/tournaments/{tournament}/standings', [PublicStatsController::class, 'standings']);
Route::get('/public/standings/featured', [PublicStatsController::class, 'featuredStandings']);
Route::get('/public/leaders', [PublicStatsController::class, 'leaders']);

Route::group(['middleware' => ['auth:sanctum']], function() {

    // AUTH
    Route::post('/logout', [AuthController::class, 'logout']);

    // USER
    Route::apiResource('/user', UserController::class);

    // ROLE
    Route::apiResource('/role', RoleController::class)->only(['index']);

    // BLOG
    Route::apiResource('/blog', BlogController::class);
    Route::apiResource('/sponsors', SponsorController::class);
    Route::apiResource('/advertising', AdvertisingController::class);

    // TEAM
    Route::get('/teams/template', [TeamController::class, 'template']);
    Route::post('/teams/import', [TeamController::class, 'import']);
    Route::apiResource('/teams', TeamController::class);

    // PLAYER
    Route::get('/players/template', [PlayerController::class, 'template']);
    Route::post('/players/import', [PlayerController::class, 'import']);
    Route::post('/players/import/preview', [PlayerController::class, 'previewImport']);
    Route::apiResource('/players', PlayerController::class);

    // FIELDING POSITION (catálogo de posiciones del roster)
    Route::get('/fielding-positions', [FieldingPositionController::class, 'index']);

    // TOURNAMENT
    Route::get('/tournaments/template', [TournamentController::class, 'template']);
    Route::post('/tournaments/import', [TournamentController::class, 'import']);
    Route::apiResource('/tournaments', TournamentController::class);

    // TOURNAMENT TEAM
    Route::get('/tournaments/{tournament}/teams', [TournamentTeamController::class, 'index']);
    Route::get('/tournaments/{tournament}/teams/available', [TournamentTeamController::class, 'available']);
    Route::get('/tournaments/{tournament}/teams/export/roster', [TournamentTeamPdfController::class, 'roster']);
    Route::get('/tournaments/{tournament}/teams/export/stats', [TournamentTeamPdfController::class, 'stats']);
    Route::post('/tournaments/{tournament}/teams', [TournamentTeamController::class, 'store']);
    Route::put('/tournaments/{tournament}/teams', [TournamentTeamController::class, 'sync']);
    Route::delete('/tournaments/{tournament}/teams/{team}', [TournamentTeamController::class, 'destroy']);
    Route::get('/tournaments/{tournament}/teams/{team}/players', [TournamentRosterController::class, 'index']);
    Route::post('/tournaments/{tournament}/teams/{team}/players', [TournamentRosterController::class, 'store']);
    Route::post('/tournaments/{tournament}/teams/{team}/players/import', [TournamentRosterController::class, 'import']);
    Route::post('/tournaments/{tournament}/teams/{team}/players/import/preview', [TournamentRosterController::class, 'previewImport']);
    Route::put('/tournaments/{tournament}/teams/{team}/players/{player}', [TournamentRosterController::class, 'update'])->whereNumber('player');
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
    Route::get('/tournaments/{tournament}/games/draft', [GameController::class, 'draft']);
    Route::put('/tournaments/{tournament}/games/draft', [GameController::class, 'saveDraft']);
    Route::delete('/tournaments/{tournament}/games/draft', [GameController::class, 'discardDraft']);
    Route::put('/tournaments/{tournament}/games/{game}', [GameController::class, 'update']);
    Route::get('/tournaments/{tournament}/games/{game}/boxscore', [BoxscoreController::class, 'show']);
    Route::put('/tournaments/{tournament}/games/{game}/boxscore', [BoxscoreController::class, 'update']);
    Route::get('/tournaments/{tournament}/games/{game}/boxscore/pdf', BoxscorePdfController::class);
    Route::post('/glossary/pdf', GlossaryPdfController::class);
    Route::delete('/tournaments/{tournament}/games', [GameController::class, 'destroy']);

     // CONTENT TYPE
    Route::apiResource('/content-type', ContentTypeController::class)->only(['index']);
});
