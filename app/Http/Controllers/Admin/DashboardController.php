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
                'messages' => ContactMessage::count(),
            ],
            'latestBookings' => Booking::with('space')->latest()->limit(6)->get(),
            'latestMessages' => ContactMessage::latest()->limit(4)->get(),
        ]);
    }
}
