@php
    $flashes = array_filter(['success' => session('success'), 'error' => session('error'), 'info' => session('info')]);
    $styles = [
        'success' => ['border-emerald-200 bg-emerald-50 text-emerald-900', 'circle-check'],
        'error' => ['border-rose-200 bg-rose-50 text-rose-900', 'circle-alert'],
        'info' => ['border-sky-200 bg-sky-50 text-sky-900', 'info'],
    ];
@endphp
@if ($flashes)
    <div class="container-page space-y-2 pt-4" role="status" aria-live="polite">
        @foreach ($flashes as $type => $message)
            <div data-flash="{{ $type }}" class="flex items-start gap-3 rounded-xl border px-4 py-3 text-sm {{ $styles[$type][0] }}">
                <i data-lucide="{{ $styles[$type][1] }}" class="mt-0.5 h-4 w-4 shrink-0"></i>
                <p class="flex-1">{{ $message }}</p>
                <button type="button" data-dismiss class="-m-1 rounded-lg p-1 opacity-70 hover:opacity-100" aria-label="Tutup"><i data-lucide="x" class="h-4 w-4"></i></button>
            </div>
        @endforeach
    </div>
@endif
