@extends('admin.layout')
@section('title', 'Pesan Masuk')
@section('heading', 'Pesan Masuk')

@section('content')
<div class="rounded-xl border border-slate-200 bg-white">
    <ul class="divide-y divide-slate-100">
        @forelse ($messages as $m)
            <li class="flex items-center gap-3 px-5 py-4 hover:bg-slate-50">
                <a href="{{ route('admin.messages.show', $m) }}" class="min-w-0 flex-1">
                    <p class="truncate text-sm font-bold">{{ $m->subject }}</p>
                    <p class="truncate text-xs text-slate-500">{{ $m->name }} &lt;{{ $m->email }}&gt; &middot; {{ $m->created_at->diffForHumans() }}</p>
                    <p class="mt-1 truncate text-sm text-slate-500">{{ $m->message }}</p>
                </a>
                <form method="POST" action="{{ route('admin.messages.destroy', $m) }}" data-confirm="Hapus pesan ini?">
                    @csrf @method('DELETE')
                    <button class="rounded-lg p-2 text-slate-400 hover:bg-red-50 hover:text-red-600" aria-label="Hapus"><i data-lucide="trash-2" class="h-4 w-4"></i></button>
                </form>
            </li>
        @empty
            <li class="px-5 py-12 text-center text-sm text-slate-400">Belum ada pesan masuk.</li>
        @endforelse
    </ul>
</div>
<div class="mt-4">{{ $messages->links() }}</div>
@endsection
