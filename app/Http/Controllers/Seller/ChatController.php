<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Services\ChatService;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function __construct(private ChatService $chat) {}

    public function index(Request $request)
    {
        $filter = $request->query('filter', 'all');
        $query = Conversation::whereHas('space', fn ($q) => $q->where('user_id', $request->user()->id));
        [$conversations, $previews] = $this->chat->inbox($query, $filter, 'unread_admin');

        return view('seller.chats.index', compact('conversations', 'filter', 'previews'));
    }

    public function show(Request $request, Conversation $conversation)
    {
        $this->own($request, $conversation);
        $conversation->load(['space', 'booking', 'user']);
        $messages = $conversation->messages()->orderBy('id')->get();
        $this->chat->markRead($conversation, 'admin');

        return view('seller.chats.show', compact('conversation', 'messages'));
    }

    public function messages(Request $request, Conversation $conversation)
    {
        $this->own($request, $conversation);

        return $this->chat->poll($request, $conversation, 'admin');
    }

    public function reply(Request $request, Conversation $conversation)
    {
        $this->own($request, $conversation);
        $data = $request->validate(['body' => ['required', 'string', 'max:1000']]);

        $message = $this->chat->send($conversation, $request->user(), $data['body']);

        if ($request->expectsJson()) {
            return response()->json(['message' => $message->toPayload()], 201);
        }

        return redirect()->route('seller.chats.show', $conversation);
    }

    public function toggle(Request $request, Conversation $conversation)
    {
        $this->own($request, $conversation);
        $this->chat->toggle($conversation);

        return redirect()->route('seller.chats.show', $conversation)
            ->with('success', $conversation->status === 'closed' ? 'Percakapan ditutup.' : 'Percakapan dibuka kembali.');
    }

    private function own(Request $request, Conversation $conversation): void
    {
        abort_unless($conversation->space?->user_id === $request->user()->id, 403);
    }
}
