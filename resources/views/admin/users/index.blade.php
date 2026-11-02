@extends('admin.layout')

@section('title', 'Pengguna')
@section('heading', 'Pengguna')

@section('content')
@php $badge = ['admin' => 'bg-violet-100 text-violet-700', 'seller' => 'bg-sky-100 text-sky-700', 'buyer' => 'bg-emerald-100 text-emerald-700']; @endphp
<form method="GET" class="mb-4 flex flex-wrap items-center gap-2">
    <input name="q" value="{{ $q }}" placeholder="Cari nama atau email..." class="w-64 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
    @foreach (['all' => 'Semua', 'admin' => 'Admin', 'seller' => 'Penjual', 'buyer' => 'Pembeli'] as $key => $label)
        <a href="{{ route('admin.users.index', array_filter(['role' => $key, 'q' => $q])) }}" class="rounded-full px-4 py-1.5 text-xs font-bold {{ $role === $key ? 'bg-brand-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-50' }}">{{ $label }}@if ($key !== 'all') ({{ $counts[$key] ?? 0 }})@endif</a>
    @endforeach
    <input type="hidden" name="role" value="{{ $role }}">
    <button class="rounded-lg bg-brand-600 px-4 py-2 text-xs font-bold text-white">Cari</button>
</form>

<div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
    <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-[11px] font-bold uppercase tracking-wide text-slate-500">
            <tr><th class="px-5 py-3">Nama</th><th class="px-5 py-3">Email</th><th class="px-5 py-3">Role</th><th class="px-5 py-3">Ruangan</th><th class="px-5 py-3">Booking</th><th class="px-5 py-3">Terdaftar</th></tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($users as $u)
                <tr class="hover:bg-slate-50">
                    <td class="px-5 py-3 font-semibold">{{ $u->name }}</td>
                    <td class="px-5 py-3 text-slate-500">{{ $u->email }}</td>
                    <td class="px-5 py-3"><span class="rounded-full px-2.5 py-1 text-[11px] font-bold {{ $badge[$u->role] ?? 'bg-slate-200 text-slate-600' }}">{{ $u->role }}</span></td>
                    <td class="px-5 py-3 text-slate-500">{{ $u->spaces_count }}</td>
                    <td class="px-5 py-3 text-slate-500">{{ $u->bookings_count }}</td>
                    <td class="px-5 py-3 text-slate-500">{{ $u->created_at?->format('d M Y') }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-5 py-12 text-center text-slate-400">Tidak ada pengguna.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $users->links() }}</div>
@endsection
