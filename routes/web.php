<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
| Простой ping для проверки, что API жив.
*/

Route::get('/', function () {
    return response()->json([
        'message' => 'SoundWave API is running',
    ]);
});
