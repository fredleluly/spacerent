@extends('seller.layout')
@section('title', 'Booking')
@section('heading', 'Booking')

@section('content')
@if (session('error'))<div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{{ session('error') }}</div>@endif
<form method="GET" class="mb-4 flex flex-wrap gap-3">
    <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari kode, nama, email..." class="w-64 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
    <select name="status" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
        <option value="">Semua Status</option>
        @foreach ($statuses as $v => $l)<option value="{{ $v }}" @selected(request('status') === $v)>{{ $l }}</option>@endforeach
    </select>
    <select name="payment_status" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
        <option value="">Semua Pembayaran</option>
        @foreach ($paymentStatuses as $v => $l)<option value="{{ $v }}" @selected(request('payment_status') === $v)>{{ $l }}</option>@endforeach
    </select>
    <button class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-bold text-white hover:bg-brand-700">Filter</button>
    @if (request()->hasAny(['q', 'status', 'payment_status']))<a href="{{ route('seller.bookings.index') }}" class="px-2 py-2 text-sm text-slate-500 hover:text-brand-600">Reset</a>@endif
</form>

<div class="rounded-xl border border-slate-200 bg-white">
    @include('seller.partials.booking-table')
</div>
<div class="mt-4">{{ $bookings->links() }}</div>
@endsection
