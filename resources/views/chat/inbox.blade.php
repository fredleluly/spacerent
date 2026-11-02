{{-- Params: $conversations, $previews, $filter, $indexRoute, $showRoute --}}
<div class="mb-4 flex flex-wrap gap-2">
    @foreach (['all' => 'Semua', 'unread' => 'Belum dibaca', 'open' => 'Open', 'closed' => 'Closed'] as $key => $label)
        <a href="{{ route($indexRoute, ['filter' => $key]) }}" class="rounded-full px-4 py-1.5 text-xs font-bold {{ $filter === $key ? 'bg-brand-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-50' }}">{{ $label }}</a>
    @endforeach
</div>

<div class="divide-y divide-slate-100 overflow-hidden rounded-xl bg-white shadow-sm">
    @forelse ($conversations as $c)
        <a href="{{ route($showRoute, $c) }}" class="flex items-start gap-4 px-5 py-4 hover:bg-slate-50">
            <div class="min-w-0 flex-1">
                <p class="flex items-center gap-2 text-sm font-bold">
                    {{ $c->user?->name ?? $c->name }}
                    <span class="text-xs font-normal text-slate-400">{{ $c->user?->email ?? $c->email }}</span>
                    <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $c->status === 'open' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' }}">{{ $c->status }}</span>
                </p>
                <p class="mt-0.5 text-xs text-slate-500">
                    {{ $c->space?->name ?? 'Tanpa ruangan' }}@if (! empty($showSeller) && $c->space?->owner) &middot; Penjual: {{ $c->space->owner->name }}@endif @if ($c->booking) &middot; Booking {{ $c->booking->code }}@endif
                </p>
                <p class="mt-1 truncate text-sm text-slate-600">{{ \Illuminate\Support\Str::limit($previews[$c->last_body_id] ?? '', 90) }}</p>
            </div>
            <div class="text-right">
                <p class="text-xs text-slate-400">{{ $c->last_message_at?->diffForHumans() }}</p>
                @if ($c->unread_admin)
                    <span class="mt-1 inline-block rounded-full bg-rose-500 px-2 py-0.5 text-[10px] font-bold text-white">{{ $c->unread_admin }}</span>
                @endif
            </div>
        </a>
    @empty
        <p class="p-8 text-center text-sm text-slate-500">Belum ada percakapan.</p>
    @endforelse
</div>
<div class="mt-4">{{ $conversations->links() }}</div>
