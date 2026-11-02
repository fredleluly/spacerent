<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\ContactMessage;
use App\Models\Space;

class DashboardController extends Controller
{
    public function index()
    {
        return view('admin.dashboard', [
            'stats' => [
                'spaces' => Space::count(),
                'pending' => Booking::where('status', 'pending')->count(),
                'bookings' => Booking::count(),
                'revenue' => Booking::where('status', 'confirmed')->sum('total'),
                'revenue_paid' => (int) Booking::where('payment_status', 'paid')->sum('total'),
                'revenue_month' => (int) Booking::where('payment_status', 'paid')
                    ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('total'),
                'awaiting_payment' => Booking::where('status', 'pending')->where('payment_status', 'unpaid')
                    ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->count(),
                'today' => Booking::where('status', 'confirmed')->whereDate('starts_at', today())->count(),
                'messages' => ContactMessage::count(),
            ],
            'latestBookings' => Booking::with('space')->latest()->limit(6)->get(),
            'latestMessages' => ContactMessage::latest()->limit(4)->get(),
        ]);
    }
}
