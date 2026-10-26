@extends('admin.layout')
@section('title', $message->subject)
@section('heading', 'Detail Pesan')

@section('content')
<a href="{{ route('admin.messages.index') }}" class="mb-4 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-brand-600"><i data-lucide="arrow-left" class="h-4 w-4"></i>Kembali</a>
<div class="max-w-3xl rounded-xl border border-slate-200 bg-white p-6">
    <h2 class="text-xl font-extrabold">{{ $message->subject }}</h2>
    <p class="mt-1 text-sm text-slate-500">{{ $message->name }} &middot; {{ $message->email }}@if ($message->phone) &middot; {{ $message->phone }}@endif &middot; {{ $message->created_at->format('d M Y H:i') }}</p>
    <p class="mt-5 whitespace-pre-line text-sm leading-relaxed text-slate-700">{{ $message->message }}</p>
    <div class="mt-6 flex gap-3">
        <a href="mailto:{{ $message->email }}?subject={{ rawurlencode('Re: '.$message->subject) }}" class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2 text-sm font-bold text-white hover:bg-brand-700"><i data-lucide="reply" class="h-4 w-4"></i>Balas via Email</a>
        <form method="POST" action="{{ route('admin.messages.destroy', $message) }}" data-confirm="Hapus pesan ini?">
            @csrf @method('DELETE')
            <button class="rounded-lg border border-red-200 px-4 py-2 text-sm font-bold text-red-600 hover:bg-red-50">Hapus</button>
        </form>
    </div>
</div>
@endsection
