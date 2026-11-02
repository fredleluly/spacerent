<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Services\ChatService;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function __construct(private ChatService $chat) {}

    public function index(Request $request)
    {
        $conversations = Conversation::where('user_id', $request->user()->id)
            ->with(['space.owner', 'booking'])
            ->withMax('messages as last_body_id', 'id')
            ->orderByDesc('last_message_at')->orderByDesc('id')
            ->paginate(15);
        $previews = ChatMessage::whereIn('id', $conversations->getCollection()->pluck('last_body_id')->filter())->pluck('body', 'id');

        return view('buyer.chats.index', compact('conversations', 'previews'));
    }

    public function show(Request $request, Conversation $conversation)
    {
        $this->own($request, $conversation);
        $conversation->load(['space.owner', 'booking']);
        $messages = $conversation->messages()->orderBy('id')->get();
        $this->chat->markRead($conversation, 'customer');

        return view('buyer.chats.show', compact('conversation', 'messages'));
    }

    public function messages(Request $request, Conversation $conversation)
    {
        $this->own($request, $conversation);

        return $this->chat->poll($request, $conversation, 'customer');
    }

    public function send(Request $request, Conversation $conversation)
    {
        $this->own($request, $conversation);
        $data = $request->validate(['body' => ['required', 'string', 'max:1000']]);

        $message = $this->chat->send($conversation, $request->user(), $data['body']);

        return response()->json(['message' => $message->toPayload()], 201);
    }

    private function own(Request $request, Conversation $conversation): void
    {
        abort_unless($conversation->user_id === $request->user()->id, 403);
    }
}
