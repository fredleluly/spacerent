<?php

namespace App\Services;

use App\Mail\ChatReceived;
use App\Mail\ChatReplied;
use App\Models\Booking;
use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\Space;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/**
 * Logika chat bersama untuk pembeli, penjual, dan admin.
 * Nilai kolom sender: 'customer' = pembeli, 'admin' = sisi penjual/admin.
 */
class ChatService
{
    /** Pembeli peserta, penjual pemilik ruangan, atau admin. */
    public function canAccess(User $user, Conversation $conversation): bool
    {
        if ($user->isAdmin()) {
            return true;
        }
        if ($conversation->user_id === $user->id) {
            return true;
        }

        return $conversation->space?->user_id === $user->id;
    }

    public function authorize(User $user, Conversation $conversation): void
    {
        abort_unless($this->canAccess($user, $conversation), 403);
    }

    public function sideFor(User $user, Conversation $conversation): string
    {
        return $conversation->user_id === $user->id ? 'customer' : 'admin';
    }

    /** Mulai (atau pakai ulang) percakapan pembeli+ruangan. */
    public function start(User $buyer, Space $space, ?Booking $booking = null, ?string $body = null): Conversation
    {
        $conversation = Conversation::where('user_id', $buyer->id)->where('space_id', $space->id)->first()
            ?? Conversation::create([
                'user_id' => $buyer->id,
                'space_id' => $space->id,
                'booking_id' => $booking?->id,
                'name' => $buyer->name,
                'email' => $buyer->email,
            ]);

        if ($booking && ! $conversation->booking_id) {
            $conversation->update(['booking_id' => $booking->id]);
        }

        if (filled($body)) {
            $this->send($conversation, $buyer, $body);
        }

        return $conversation;
    }

    public function send(Conversation $conversation, User $user, string $body): ChatMessage
    {
        $side = $this->sideFor($user, $conversation);
        $previous = $conversation->messages()->latest('id')->first();
        $previousSame = $conversation->messages()->where('sender', $side)->latest('id')->first();

        $message = $conversation->messages()->create([
            'sender' => $side,
            'user_id' => $user->id,
            'body' => trim($body),
        ]);

        if ($side === 'customer') {
            $conversation->forceFill([
                'unread_admin' => $conversation->unread_admin + 1,
                'last_message_at' => now(),
                'status' => 'open',
            ])->save();

            if (! $previous || $previous->created_at->lt(now()->subMinutes(5))) {
                $this->mail($conversation->space?->owner?->email, new ChatReceived($conversation, $message));
            }
        } else {
            $conversation->forceFill([
                'unread_customer' => $conversation->unread_customer + 1,
                'last_message_at' => now(),
            ])->save();

            if (! $previousSame || $previousSame->created_at->lt(now()->subMinutes(5))) {
                $this->mail($conversation->user?->email ?? $conversation->email, new ChatReplied($conversation, $message));
            }
        }

        return $message;
    }

    private function mail(?string $to, $mailable): void
    {
        if (! $to) {
            return;
        }
        try {
            Mail::to($to)->send($mailable);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Tandai pesan lawan bicara terbaca untuk sisi $side. */
    public function markRead(Conversation $conversation, string $side): void
    {
        $other = $side === 'customer' ? 'admin' : 'customer';
        $conversation->messages()->where('sender', $other)->whereNull('read_at')->update(['read_at' => now()]);

        $field = $side === 'customer' ? 'unread_customer' : 'unread_admin';
        if ($conversation->{$field}) {
            $conversation->forceFill([$field => 0])->save();
        }
    }

    public function toggle(Conversation $conversation): void
    {
        $conversation->update(['status' => $conversation->status === 'open' ? 'closed' : 'open']);
    }

    /** JSON polling: pesan setelah id tertentu. */
    public function poll(Request $request, Conversation $conversation, string $side)
    {
        $after = (int) $request->query('after', 0);
        $messages = $conversation->messages()->where('id', '>', $after)->orderBy('id')->get();
        $this->markRead($conversation, $side);

        return response()->json([
            'messages' => $messages->map->toPayload()->values(),
            'status' => $conversation->status,
        ]);
    }

    /** Daftar inbox + preview pesan terakhir. [paginator, previews] */
    public function inbox(Builder $query, string $filter, string $unreadField): array
    {
        $conversations = $query->with(['space.owner', 'booking', 'user'])
            ->withMax('messages as last_body_id', 'id')
            ->when($filter === 'unread', fn ($q) => $q->where($unreadField, '>', 0))
            ->when(in_array($filter, ['open', 'closed']), fn ($q) => $q->where('status', $filter))
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $previews = ChatMessage::whereIn('id', $conversations->getCollection()->pluck('last_body_id')->filter())->pluck('body', 'id');

        return [$conversations, $previews];
    }
}
