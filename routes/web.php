<?php

use App\Http\Controllers\BookingController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\SpaceController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SpaceController::class, 'index'])->name('home');
Route::get('/ruangan/{space}', [SpaceController::class, 'show'])->name('spaces.show');

Route::post('/ruangan/{space}/booking', [BookingController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('booking.store');
Route::get('/booking/{booking}', [BookingController::class, 'show'])->name('booking.show');

Route::get('/syarat-ketentuan', [PageController::class, 'terms'])->name('terms');
Route::get('/faq', [PageController::class, 'faq'])->name('faq');
Route::get('/hubungi-kami', [PageController::class, 'contact'])->name('contact');
Route::post('/hubungi-kami', [PageController::class, 'sendContact'])
    ->middleware('throttle:5,1')
    ->name('contact.send');
