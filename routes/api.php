<?php

use App\Http\Controllers\OrderController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::prefix("orders")->group(function () {
    Route::post("/", [OrderController::class, "store"]);
    Route::get("/{id}", [OrderController::class, "show"]);
});

Route::get("/user/{id}/orders", [OrderController::class, "userOrders"]);
Route::get("/user/{id}/sales", [OrderController::class, "userSales"]);
Route::post("/checkout", [OrderController::class, "checkout"]);
