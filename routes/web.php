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

use App\Livewire\CharacterCreationWizard;

Route::get('/crea-personaggio', CharacterCreationWizard::class)->name('character.create');

Route::get('/personaggi/{character}/modifica', CharacterCreationWizard::class)
    ->middleware('auth')
    ->name('character.edit');

use App\Http\Controllers\Auth\RegisterController;

Route::get('/registrati', [RegisterController::class, 'create'])->name('register');
Route::post('/registrati', [RegisterController::class, 'store'])->name('register.store');

use App\Http\Controllers\Auth\LoginController;

Route::get('/accedi', [LoginController::class, 'create'])->name('login');
Route::post('/accedi', [LoginController::class, 'store'])->name('login.store');

use App\Http\Controllers\Auth\LogoutController;

Route::post('/esci', [LogoutController::class, 'store'])->middleware('auth')->name('logout');

Route::get('/crediti', function () {
    return view('credits.index');
})->middleware('auth')->name('credits.index');

use App\Livewire\CharacterList;
use App\Livewire\CharacterDetail;

Route::get('/personaggi', CharacterList::class)
    ->middleware('auth')
    ->name('character.panel');

Route::get('/personaggi/{character}', CharacterDetail::class)
    ->middleware('auth')
    ->name('character.show');
