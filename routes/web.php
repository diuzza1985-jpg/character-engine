<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

use App\Http\Controllers\InstagramAuthController;

Route::get('/instagram/connect/{character}', [InstagramAuthController::class, 'redirect'])->name('instagram.connect');
Route::get('/instagram/callback', [InstagramAuthController::class, 'callback'])->name('instagram.callback');

use App\Http\Controllers\MediaController;

Route::get('/media/{post}/{index?}', [MediaController::class, 'show'])->name('media.show')->middleware('signed');
