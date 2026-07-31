<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\UserController;

use App\Http\Controllers\Api\Manufacture\MakingHouseController;

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });

Route::post('/login', [AuthController::class, 'login']);

// Route::middleware(['auth:sanctum', 'active_user'])->group(function () {

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::apiResource('roles', RoleController::class);
    Route::post('/roles/{role}/permissions', [RoleController::class, 'syncPermissions']);
    Route::get('/permissions', [RoleController::class, 'allPermissions']);
    Route::get('/permissions-list', [RoleController::class, 'paginatedList']);

    Route::apiResource('users', UserController::class);

    Route::get('/making-houses', [MakingHouseController::class, 'index'])->middleware('permission:manufacturing.making-house.view');
    Route::post('/making-houses', [MakingHouseController::class, 'store'])->middleware('permission:manufacturing.making-house.create');
    Route::put('/making-houses/{makingHouse}', [MakingHouseController::class, 'update'])->middleware('permission:manufacturing.making-house.edit');
    Route::delete('/making-houses/{makingHouse}', [MakingHouseController::class, 'destroy'])->middleware('permission:manufacturing.making-house.delete');
});
// });
