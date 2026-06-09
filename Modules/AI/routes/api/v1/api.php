<?php

use Illuminate\Support\Facades\Route;
use Modules\AI\app\Http\Controllers\Api\ProductAutoFillController;
use Modules\AI\app\Http\Controllers\Api\V1\AiController;

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

Route::group(['prefix' => 'ai', 'as' => 'ai.', 'middleware' => ['vendor.api', 'actch:vendor_app']], function () {
    // Route::get('generate-food-data', [ProductAutoFillController::class, 'getData']);
    Route::get('generate-title-and-description', [ProductAutoFillController::class, 'getTitleAndDescription']);
    Route::get('generate-other-data', [ProductAutoFillController::class, 'getOtherData']);
    Route::get('generate-variation-data', [ProductAutoFillController::class, 'getVariationData']);
    Route::get('generate-title-suggestions', [ProductAutoFillController::class, 'generateTitleSuggestions']);
    Route::post('generate-form-image', [ProductAutoFillController::class, 'analyzeImageAutoFill']);
});

Route::prefix('ai')->name('ai.shopping.')->group(function () {
    Route::post('chat', [AiController::class, 'chat'])->middleware('throttle:30,1')->name('chat');
    Route::post('search/products', [AiController::class, 'searchProducts'])->middleware('throttle:60,1')->name('search-products');
    Route::post('recommendations/events', [AiController::class, 'recommendationEvent'])->middleware('throttle:120,1')->name('recommendation-events');
});
