<?php

use Illuminate\Http\Request;

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

//Route::middleware('auth:api')->get('/internalapi', function (Request $request) {
//    return $request->user();
//});
Route::prefix('internalApi')->middleware('inner.api')->group(function () {
	Route::post('/contentReview', 'InternalApiController@contentReview');
	Route::post('/spot/image', 'InternalApiController@spot');
	Route::post('/transcode/callback', 'InternalApiController@transcodeCallback');
});
