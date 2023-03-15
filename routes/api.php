<?php

use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

use PHPUnit\TextUI\XmlConfiguration\Group;

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


Route::prefix('user/')
    ->name('user.')
    ->group(function () {

        Route::post('create', [UserController::class, 'create'])
            ->name('create');

        Route::post('update/{userId}',  [UserController::class, 'update'])
            ->name('update');

        Route::post('update-bridge/{userId}',  [UserController::class, 'updateBridge'])
            ->name('update_bridge');

        Route::post('update-sync/{userId}',  [UserController::class, 'updateSync'])
            ->name('update_sync');

        Route::get('delete/{userId}',  [UserController::class, 'delete'])
            ->name('delete');

        Route::post('upsert/{userId}',  [UserController::class, 'upsert'])
            ->name('upsert');

        Route::post('create-card/{userId}',  [UserController::class, 'createCard'])
            ->name('create_card');
            Route::post('update-default-card/{userId}',  [UserController::class, 'updateDefaultCard'])
            ->name('update_default_card');
            
        Route::get('cards/{userId}',  [UserController::class, 'getCards'])
            ->name('get_cards');

        Route::get('card-default/{userId}',  [UserController::class, 'getCardDefault'])
            ->name('get_card_default');
        Route::post('payment-pm-default/{userId}',  [UserController::class, 'paymentPMDefault'])
            ->name('payment_pm_default');
        Route::post('payment-guest',  [UserController::class, 'paymentGuest'])
            ->name('payment_guest');
            Route::post('payment-intent/{userId}',  [UserController::class, 'paymentIntent'])
            ->name('payment_intent');
    });

