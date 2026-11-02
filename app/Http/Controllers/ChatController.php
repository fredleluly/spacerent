<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Space;
use App\Services\ChatService;
use Illuminate\Http\Request;

/** Memulai chat (khusus pembeli yang login). */
class ChatController extends Controller
{
    public function __construct(private ChatService $chat) {}

    public function startForSpace(Request $request, Space $space)
    {
        $data = $request->validate(['body' => ['nullable', 'string', 'max:1000']]);

        $conversation = $this->chat->start($request->user(), $space, null, $data['body'] ?? null);

        return redirect()->route('buyer.chats.show', $conversation);
    }

    public function startForBooking(Request $request, Booking $booking)
    {
        abort_unless($booking->user_id === $request->user()->id, 403);
        abort_unless($booking->space, 404);
        $data = $request->validate(['body' => ['nullable', 'string', 'max:1000']]);

        $conversation = $this->chat->start($request->user(), $booking->space, $booking, $data['body'] ?? null);

        return redirect()->route('buyer.chats.show', $conversation);
    }
}
