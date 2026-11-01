<?php

use App\Http\Controllers\BookingController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\MidtransWebhookController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\SpaceController;
use App\Http\Controllers\Admin;
use Illuminate\Support\Facades\Route;

Route::get('/', [SpaceController::class, 'index'])->name('home');
Route::get('/ruangan/{space}', [SpaceController::class, 'show'])->name('spaces.show');

Route::post('/ruangan/{space}/booking', [BookingController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('booking.store');
Route::get('/booking/{booking}', [BookingController::class, 'show'])->name('booking.show');

// Pembayaran (Midtrans)
Route::post('/booking/{booking}/pay', [PaymentController::class, 'pay'])->middleware('throttle:20,1')->name('booking.pay');
Route::get('/booking/{booking}/finish', [PaymentController::class, 'finish'])->name('booking.finish');
Route::post('/midtrans/notification', [MidtransWebhookController::class, 'handle'])->name('midtrans.notification');

// Chat customer <-> pemilik
Route::post('/ruangan/{space}/chat', [ChatController::class, 'startForSpace'])->middleware('throttle:10,1')->name('chat.start.space');
Route::post('/booking/{booking}/chat', [ChatController::class, 'startForBooking'])->middleware('throttle:10,1')->name('chat.start.booking');
Route::get('/chat/{token}', [ChatController::class, 'show'])->name('chat.show');
Route::get('/chat/{token}/messages', [ChatController::class, 'messages'])->name('chat.messages');
Route::post('/chat/{token}/messages', [ChatController::class, 'send'])->middleware('throttle:20,1')->name('chat.send');

Route::get('/syarat-ketentuan', [PageController::class, 'terms'])->name('terms');
Route::get('/faq', [PageController::class, 'faq'])->name('faq');
Route::get('/hubungi-kami', [PageController::class, 'contact'])->name('contact');
Route::post('/hubungi-kami', [PageController::class, 'sendContact'])
    ->middleware('throttle:5,1')
    ->name('contact.send');

/*
|--------------------------------------------------------------------------
| Admin
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('login', [Admin\AuthController::class, 'showLogin'])->name('login');
        Route::post('login', [Admin\AuthController::class, 'login'])->middleware('throttle:10,1');
    });

    Route::middleware('auth')->group(function () {
        Route::redirect('/', '/admin/dashboard');
        Route::post('logout', [Admin\AuthController::class, 'logout'])->name('logout');
        Route::get('dashboard', [Admin\DashboardController::class, 'index'])->name('dashboard');

        Route::resource('spaces', Admin\SpaceController::class)->except('show');

        Route::get('bookings', [Admin\BookingController::class, 'index'])->name('bookings.index');
        Route::get('bookings/{booking}', [Admin\BookingController::class, 'show'])->name('bookings.show');
        Route::patch('bookings/{booking}', [Admin\BookingController::class, 'update'])->name('bookings.update');
        Route::delete('bookings/{booking}', [Admin\BookingController::class, 'destroy'])->name('bookings.destroy');

        Route::get('chats', [Admin\ChatController::class, 'index'])->name('chats.index');
        Route::get('chats/{conversation}', [Admin\ChatController::class, 'show'])->name('chats.show');
        Route::get('chats/{conversation}/messages', [Admin\ChatController::class, 'messages'])->name('chats.messages');
        Route::post('chats/{conversation}/messages', [Admin\ChatController::class, 'reply'])->name('chats.reply');
        Route::patch('chats/{conversation}', [Admin\ChatController::class, 'toggle'])->name('chats.toggle');

        Route::get('messages', [Admin\MessageController::class, 'index'])->name('messages.index');
        Route::get('messages/{message}', [Admin\MessageController::class, 'show'])->name('messages.show');
        Route::delete('messages/{message}', [Admin\MessageController::class, 'destroy'])->name('messages.destroy');
    });
});
