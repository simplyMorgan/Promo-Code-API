<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PromoCodeController;

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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::group(['prefix' => 'promo-code'], function(){

    Route::group(['prefix' => 'private', 'middleware' => 'auth:api'], function(){

        Route::get('/', [PromoCodeController::class, 'index'])->name('fetch.all.promo.codes');
        Route::get('/show-active', [PromoCodeController::class, 'showActivePromoCodes'])->name('fetch.active.promo.codes');
    
        Route::put('/deactivate/{code}', [PromoCodeController::class, 'deactivatePromoCode'])->name('deactivate.promo.code');
        Route::post('/generate', [PromoCodeController::class, 'create'])->name('generate.promo.code');
    });

    Route::post('/check-validity', [PromoCodeController::class, 'checkPromoCodeValidity'])->name('check.promo.code.validity');

});

