<?php

namespace Tests\Feature;

use App\Mail\ChatReceived;
use App\Mail\ChatReplied;
use App\Models\Booking;
use App\Models\Conversation;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, string $name = null): User
    {
        $name ??= ucfirst($role).rand(100, 999);

        return User::create(['name' => $name, 'email' => strtolower($name).'@x.com', 'password' => 'secret123', 'role' => $role]);
    }

    private function space(User $seller, string $slug = 'ruang-a'): Space
    {
        return Space::create([
            'name' => 'Ruang '.$slug, 'slug' => $slug, 'category' => 'meeting-room', 'area' => 'Jakarta Pusat',
            'district' => 'Menteng', 'capacity' => 8, 'price' => 100000, 'price_unit' => 'jam',
            'image' => 'x.jpg', 'description' => 'desc', 'user_id' => $seller->id,
        ]);
    }

    private function booking(User $buyer, Space $space): Booking
    {
        return Booking::create([
            'code' => 'SR-TEST1', 'space_id' => $space->id, 'user_id' => $buyer->id, 'name' => $buyer->name,
            'email' => $buyer->email, 'phone' => '0812', 'date' => now()->addDay()->toDateString(),
            'start_time' => '09:00', 'duration' => 2, 'guests' => 2,
            'total' => 200000, 'status' => 'pending',
        ]);
    }

    /** @return array{User,User,Space,Conversation} buyer, seller, space, conversation */
    private function setupChat(): array
    {
        $seller = $this->user('seller', 'Sella');
        $buyer = $this->user('buyer', 'Budi');
        $space = $this->space($seller);
        $c = Conversation::create(['user_id' => $buyer->id, 'space_id' => $space->id, 'name' => 'Budi', 'email' => $buyer->email]);

        return [$buyer, $seller, $space, $c];
    }

    public function test_guest_redirected_to_login(): void
    {
        $space = $this->space($this->user('seller'));
        $this->post(route('chat.start.space', $space), ['body' => 'Halo'])->assertRedirect(route('login'));
        $this->get(route('buyer.chats.index'))->assertRedirect(route('login'));
    }

    public function test_buyer_starts_chat_from_space_and_reuses(): void
    {
        Mail::fake();
        $seller = $this->user('seller');
        $buyer = $this->user('buyer');
        $space = $this->space($seller);

        $r = $this->actingAs($buyer)->post(route('chat.start.space', $space), ['body' => 'Apakah ada proyektor?']);
        $c = Conversation::firstOrFail();
        $r->assertRedirect(route('buyer.chats.show', $c));
        $this->assertSame($buyer->id, $c->user_id);
        $this->assertSame(1, $c->unread_admin);
        $this->assertSame('customer', $c->messages()->first()->sender);
        Mail::assertSent(ChatReceived::class, fn ($m) => $m->hasTo($seller->email));

        $this->actingAs($buyer)->post(route('chat.start.space', $space), ['body' => 'Lagi']);
        $this->assertSame(1, Conversation::count());
        $this->assertSame(2, $c->messages()->count());
    }

    public function test_start_from_booking_attaches_and_requires_ownership(): void
    {
        $seller = $this->user('seller');
        $buyer = $this->user('buyer');
        $other = $this->user('buyer');
        $space = $this->space($seller);
        $booking = $this->booking($buyer, $space);

        $this->actingAs($other)->post(route('chat.start.booking', $booking))->assertForbidden();

        // chat sudah ada dari halaman ruangan -> booking ditempelkan
        $this->actingAs($buyer)->post(route('chat.start.space', $space), ['body' => 'Hi']);
        $this->actingAs($buyer)->post(route('chat.start.booking', $booking))->assertRedirect();
        $this->assertSame(1, Conversation::count());
        $this->assertSame($booking->id, Conversation::first()->booking_id);
    }

    public function test_seller_and_admin_cannot_start_chat(): void
    {
        $seller = $this->user('seller');
        $space = $this->space($seller);
        $this->actingAs($seller)->post(route('chat.start.space', $space))->assertForbidden();
        $this->actingAs($this->user('admin'))->post(route('chat.start.space', $space))->assertForbidden();
    }

    public function test_body_validation_1000_chars(): void
    {
        [$buyer, , , $c] = $this->setupChat();
        $this->actingAs($buyer)->postJson(route('buyer.chats.send', $c), ['body' => str_repeat('a', 1001)])->assertStatus(422);
        $this->actingAs($buyer)->postJson(route('buyer.chats.send', $c), ['body' => ''])->assertStatus(422);
        $this->actingAs($buyer)->postJson(route('buyer.chats.send', $c), ['body' => str_repeat('a', 1000)])->assertCreated();
    }

    public function test_unauthorized_users_get_403(): void
    {
        [, , , $c] = $this->setupChat();
        $otherBuyer = $this->user('buyer');
        $otherSeller = $this->user('seller');

        $this->actingAs($otherBuyer)->get(route('buyer.chats.show', $c))->assertForbidden();
        $this->actingAs($otherBuyer)->getJson(route('buyer.chats.messages', $c))->assertForbidden();
        $this->actingAs($otherBuyer)->postJson(route('buyer.chats.send', $c), ['body' => 'x'])->assertForbidden();
        $this->actingAs($otherSeller)->get(route('seller.chats.show', $c))->assertForbidden();
        $this->actingAs($otherSeller)->getJson(route('seller.chats.messages', $c))->assertForbidden();
        $this->actingAs($otherSeller)->postJson(route('seller.chats.reply', $c), ['body' => 'x'])->assertForbidden();
        $this->actingAs($otherSeller)->patch(route('seller.chats.toggle', $c))->assertForbidden();
        $this->assertSame(0, $c->messages()->count());
    }

    public function test_seller_only_sees_own_chats(): void
    {
        [, $seller, , $c] = $this->setupChat();
        $other = $this->user('seller', 'Otto');
        $buyer2 = $this->user('buyer', 'Cici');
        $s2 = $this->space($other, 'ruang-b');
        $c2 = Conversation::create(['user_id' => $buyer2->id, 'space_id' => $s2->id, 'name' => 'Cici', 'email' => $buyer2->email]);
        $c->messages()->create(['sender' => 'customer', 'body' => 'pesan-milik-sella']);
        $c2->messages()->create(['sender' => 'customer', 'body' => 'pesan-milik-otto']);

        $this->actingAs($seller)->get(route('seller.chats.index'))
            ->assertOk()->assertSee('Budi')->assertDontSee('Cici');
        $this->actingAs($seller)->get(route('seller.chats.show', $c2))->assertForbidden();
        $this->actingAs($seller)->get(route('seller.chats.show', $c))->assertOk()->assertSee('pesan-milik-sella');
    }

    public function test_admin_sees_all(): void
    {
        [, , , $c] = $this->setupChat();
        $other = $this->user('seller');
        $b2 = $this->user('buyer', 'Dedi');
        $c2 = Conversation::create(['user_id' => $b2->id, 'space_id' => $this->space($other, 'ruang-c')->id, 'name' => 'Dedi', 'email' => $b2->email]);
        $admin = $this->user('admin');

        $this->actingAs($admin)->get(route('admin.chats.index'))->assertOk()->assertSee('Budi')->assertSee('Dedi');
        $this->actingAs($admin)->get(route('admin.chats.show', $c2))->assertOk();
        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk()->assertSee('Dedi');
    }

    public function test_buyer_cannot_use_seller_or_admin_routes(): void
    {
        [$buyer, , , $c] = $this->setupChat();
        $this->actingAs($buyer)->get(route('seller.chats.index'))->assertForbidden();
        $this->actingAs($buyer)->get(route('admin.chats.show', $c))->assertForbidden();
    }

    public function test_polling_after_and_unread_counters_both_ways(): void
    {
        Mail::fake();
        [$buyer, $seller, , $c] = $this->setupChat();

        $first = $this->actingAs($buyer)->postJson(route('buyer.chats.send', $c), ['body' => 'Halo'])->json('message.id');
        $this->assertSame(1, $c->fresh()->unread_admin);

        // seller membuka -> unread_admin direset
        $this->actingAs($seller)->get(route('seller.chats.show', $c))->assertOk();
        $this->assertSame(0, $c->fresh()->unread_admin);

        $reply = $this->actingAs($seller)->postJson(route('seller.chats.reply', $c), ['body' => 'Ada'])->assertCreated()->json('message.id');
        $this->assertSame('admin', $c->messages()->find($reply)->sender);
        $this->assertSame($seller->id, $c->messages()->find($reply)->user_id);
        $this->assertSame(1, $c->fresh()->unread_customer);
        Mail::assertSent(ChatReplied::class, fn ($m) => $m->hasTo($buyer->email));

        // polling buyer: hanya pesan setelah $first, dan menandai terbaca
        $r = $this->actingAs($buyer)->getJson(route('buyer.chats.messages', ['conversation' => $c, 'after' => $first]))->assertOk();
        $this->assertCount(1, $r->json('messages'));
        $this->assertSame('Ada', $r->json('messages.0.body'));
        $this->assertSame(0, $c->fresh()->unread_customer);

        $this->actingAs($buyer)->getJson(route('buyer.chats.messages', ['conversation' => $c, 'after' => $reply]))
            ->assertOk()->assertJsonCount(0, 'messages');
    }

    public function test_buyer_message_reopens_closed_and_seller_can_toggle(): void
    {
        [$buyer, $seller, , $c] = $this->setupChat();
        $this->actingAs($seller)->patch(route('seller.chats.toggle', $c))->assertRedirect();
        $this->assertSame('closed', $c->fresh()->status);

        $this->actingAs($buyer)->postJson(route('buyer.chats.send', $c), ['body' => 'Halo lagi'])->assertCreated();
        $this->assertSame('open', $c->fresh()->status);
    }

    public function test_buyer_inbox_and_escaping(): void
    {
        [$buyer, , , $c] = $this->setupChat();
        $c->messages()->create(['sender' => 'admin', 'body' => '<script>alert(1)</script>']);
        $c->update(['unread_customer' => 1]);

        $this->actingAs($buyer)->get(route('buyer.chats.index'))->assertOk()->assertSee('Ruang ruang-a');
        $this->actingAs($buyer)->get(route('buyer.chats.show', $c))->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false);
        $this->assertSame(0, $c->fresh()->unread_customer);
    }

    public function test_old_token_routes_are_gone(): void
    {
        [, , , $c] = $this->setupChat();
        $this->get('/chat/'.$c->token)->assertNotFound();
    }

    public function test_message_send_throttled(): void
    {
        [$buyer, , , $c] = $this->setupChat();
        for ($i = 0; $i < 20; $i++) {
            $this->actingAs($buyer)->postJson(route('buyer.chats.send', $c), ['body' => 'x'])->assertCreated();
        }
        $this->actingAs($buyer)->postJson(route('buyer.chats.send', $c), ['body' => 'x'])->assertStatus(429);
    }
}
