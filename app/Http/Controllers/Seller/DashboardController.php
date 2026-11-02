<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Booking;

class DashboardController extends Controller
{
    public function index()
    {
        $own = fn () => Booking::whereHas('space', fn ($q) => $q->where('user_id', auth()->id()));

        return view('seller.dashboard', [
            'stats' => [
                'spaces' => auth()->user()->spaces()->count(),
                'revenue_paid' => (int) $own()->where('payment_status', 'paid')->sum('total'),
                'revenue_month' => (int) $own()->where('payment_status', 'paid')
                    ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('total'),
                'awaiting_payment' => $own()->where('status', 'pending')->where('payment_status', 'unpaid')
                    ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->count(),
                'upcoming' => $own()->where('status', 'confirmed')->where('starts_at', '>=', today())->count(),
            ],
            'latestBookings' => $own()->with('space')->latest()->limit(6)->get(),
        ]);
    }
}
