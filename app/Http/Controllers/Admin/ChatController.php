<?php

namespace App\Http\Controllers\Admin;

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
        [$conversations, $previews] = $this->chat->inbox(Conversation::query(), $filter, 'unread_admin');

        return view('admin.chats.index', compact('conversations', 'filter', 'previews'));
    }

    public function show(Conversation $conversation)
    {
        $conversation->load(['space.owner', 'booking', 'user']);
        $messages = $conversation->messages()->orderBy('id')->get();
        $this->chat->markRead($conversation, 'admin');

        return view('admin.chats.show', compact('conversation', 'messages'));
    }

    public function messages(Request $request, Conversation $conversation)
    {
        return $this->chat->poll($request, $conversation, 'admin');
    }

    public function reply(Request $request, Conversation $conversation)
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:1000']]);

        $message = $this->chat->send($conversation, $request->user(), $data['body']);

        if ($request->expectsJson()) {
            return response()->json(['message' => $message->toPayload()], 201);
        }

        return redirect()->route('admin.chats.show', $conversation);
    }

    public function toggle(Conversation $conversation)
    {
        $this->chat->toggle($conversation);

        return redirect()->route('admin.chats.show', $conversation)
            ->with('success', $conversation->status === 'closed' ? 'Percakapan ditutup.' : 'Percakapan dibuka kembali.');
    }
}
