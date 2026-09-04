<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\ContentTypeController;

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

     // CONTENT TYPE
    Route::apiResource('/content-type', ContentTypeController::class)->only(['index']);
});
