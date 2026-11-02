<?php

use App\Http\Controllers\Auth;
use App\Http\Controllers\Buyer;
use App\Http\Controllers\Seller;
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

Route::get('/ruangan/{space}/availability', [BookingController::class, 'availability'])->name('spaces.availability');
// Autentikasi pembeli & penjual
Route::middleware('guest')->group(function () {
    Route::get('/daftar', [Auth\RegisterController::class, 'create'])->name('register');
    Route::post('/daftar', [Auth\RegisterController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/masuk', [Auth\LoginController::class, 'create'])->name('login');
    Route::post('/masuk', [Auth\LoginController::class, 'store'])->middleware('throttle:10,1');
});
Route::post('/keluar', [Auth\LoginController::class, 'destroy'])->middleware('auth')->name('logout');

// Pembeli (wajib login, role buyer)
Route::middleware(['auth', 'role:buyer'])->group(function () {
    Route::post('/ruangan/{space}/booking', [BookingController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('booking.store');
    Route::get('/akun/pesanan', [Buyer\BookingController::class, 'index'])->name('buyer.bookings.index');
    Route::get('/akun/chat', [Buyer\ChatController::class, 'index'])->name('buyer.chats.index');
    Route::get('/akun/chat/{conversation}', [Buyer\ChatController::class, 'show'])->name('buyer.chats.show');
    Route::get('/akun/chat/{conversation}/messages', [Buyer\ChatController::class, 'messages'])->name('buyer.chats.messages');
    Route::post('/akun/chat/{conversation}/messages', [Buyer\ChatController::class, 'send'])->middleware('throttle:20,1')->name('buyer.chats.send');
    Route::post('/ruangan/{space}/chat', [ChatController::class, 'startForSpace'])->middleware('throttle:10,1')->name('chat.start.space');
    Route::post('/booking/{booking}/chat', [ChatController::class, 'startForBooking'])->middleware('throttle:10,1')->name('chat.start.booking');
    Route::get('/akun/profil', [Buyer\ProfileController::class, 'edit'])->name('buyer.profile');
    Route::put('/akun/profil', [Buyer\ProfileController::class, 'update']);
});
// Detail booking: pemilik booking (pembeli), penjual ruangan tsb, atau admin (otorisasi di controller)
Route::get('/booking/{booking}', [BookingController::class, 'show'])->middleware('auth')->name('booking.show');

// Pembayaran (Midtrans)
Route::post('/booking/{booking}/pay', [PaymentController::class, 'pay'])->middleware(['auth', 'role:buyer', 'throttle:20,1'])->name('booking.pay');
Route::get('/booking/{booking}/finish', [PaymentController::class, 'finish'])->middleware('auth')->name('booking.finish');
Route::post('/midtrans/notification', [MidtransWebhookController::class, 'handle'])->name('midtrans.notification');

Route::get('/syarat-ketentuan', [PageController::class, 'terms'])->name('terms');
Route::get('/faq', [PageController::class, 'faq'])->name('faq');
Route::get('/hubungi-kami', [PageController::class, 'contact'])->name('contact');
Route::post('/hubungi-kami', [PageController::class, 'sendContact'])
    ->middleware('throttle:5,1')
    ->name('contact.send');

/*
|--------------------------------------------------------------------------
| Penjual (pemilik ruangan)
|--------------------------------------------------------------------------
*/
Route::prefix('penjual')->name('seller.')->middleware(['auth', 'role:seller'])->group(function () {
    Route::get('/', fn () => redirect()->route('seller.dashboard'));
    Route::get('dashboard', [Seller\DashboardController::class, 'index'])->name('dashboard');
    Route::resource('ruangan', Seller\SpaceController::class)->except('show')->names('spaces')->parameters(['ruangan' => 'space']);

    Route::get('bookings', [Seller\BookingController::class, 'index'])->name('bookings.index');
    Route::get('bookings/{booking}', [Seller\BookingController::class, 'show'])->name('bookings.show');
    Route::post('bookings/{booking}/cancel', [Seller\BookingController::class, 'cancel'])->name('bookings.cancel');
    Route::post('bookings/{booking}/complete', [Seller\BookingController::class, 'complete'])->name('bookings.complete');

    Route::get('chat', [Seller\ChatController::class, 'index'])->name('chats.index');
    Route::get('chat/{conversation}', [Seller\ChatController::class, 'show'])->name('chats.show');
    Route::get('chat/{conversation}/messages', [Seller\ChatController::class, 'messages'])->name('chats.messages');
    Route::post('chat/{conversation}/messages', [Seller\ChatController::class, 'reply'])->middleware('throttle:20,1')->name('chats.reply');
    Route::patch('chat/{conversation}', [Seller\ChatController::class, 'toggle'])->name('chats.toggle');
});

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

    Route::middleware(['auth', 'role:admin'])->group(function () {
        Route::redirect('/', '/admin/dashboard');
        Route::post('logout', [Admin\AuthController::class, 'logout'])->name('logout');
        Route::get('dashboard', [Admin\DashboardController::class, 'index'])->name('dashboard');

        Route::resource('spaces', Admin\SpaceController::class)->except('show');

        Route::get('bookings', [Admin\BookingController::class, 'index'])->name('bookings.index');
        Route::get('bookings/{booking}', [Admin\BookingController::class, 'show'])->name('bookings.show');
        Route::patch('bookings/{booking}', [Admin\BookingController::class, 'update'])->name('bookings.update');
        Route::post('bookings/{booking}/cancel', [Admin\BookingController::class, 'cancel'])->name('bookings.cancel');
        Route::post('bookings/{booking}/refund', [Admin\BookingController::class, 'refund'])->name('bookings.refund');
        Route::post('bookings/{booking}/complete', [Admin\BookingController::class, 'complete'])->name('bookings.complete');
        Route::delete('bookings/{booking}', [Admin\BookingController::class, 'destroy'])->name('bookings.destroy');

        Route::get('chats', [Admin\ChatController::class, 'index'])->name('chats.index');
        Route::get('chats/{conversation}', [Admin\ChatController::class, 'show'])->name('chats.show');
        Route::get('chats/{conversation}/messages', [Admin\ChatController::class, 'messages'])->name('chats.messages');
        Route::post('chats/{conversation}/messages', [Admin\ChatController::class, 'reply'])->middleware('throttle:20,1')->name('chats.reply');
        Route::patch('chats/{conversation}', [Admin\ChatController::class, 'toggle'])->name('chats.toggle');

        Route::get('users', [Admin\UserController::class, 'index'])->name('users.index');

        Route::get('messages', [Admin\MessageController::class, 'index'])->name('messages.index');
        Route::get('messages/{message}', [Admin\MessageController::class, 'show'])->name('messages.show');
        Route::delete('messages/{message}', [Admin\MessageController::class, 'destroy'])->name('messages.destroy');
    });
});
