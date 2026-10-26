<a href="{{ route('spaces.show', $space) }}" class="group flex flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-xl">
    <div class="relative h-48 overflow-hidden bg-slate-200">
        <img src="{{ $space->image_url }}" alt="{{ $space->name }}" loading="lazy"
             class="h-full w-full object-cover transition duration-500 group-hover:scale-105"
             onerror="this.style.display='none'">
        <span class="absolute left-3 top-3 rounded-md bg-slate-900/85 px-2.5 py-1 text-[11px] font-bold text-white">{{ $space->category_label }}</span>
        <span class="absolute right-3 top-3 rounded-md px-2.5 py-1 text-[11px] font-bold text-white {{ $space->is_available ? 'bg-emerald-500' : 'bg-slate-500' }}">
            {{ $space->is_available ? 'Tersedia' : 'Penuh' }}
        </span>
    </div>
    <div class="flex flex-1 flex-col p-4">
        <p class="flex items-center gap-1.5 text-xs text-slate-500">
            <i data-lucide="map-pin" class="h-3.5 w-3.5 text-brand-600"></i>{{ $space->location }}
        </p>
        <h3 class="mt-1.5 text-base font-bold leading-snug text-ink group-hover:text-brand-600">{{ $space->name }}</h3>
        <div class="mt-3 flex items-center gap-4 text-xs text-slate-500">
            <span class="inline-flex items-center gap-1"><i data-lucide="users" class="h-3.5 w-3.5"></i>{{ $space->capacity }} orang</span>
            <span class="inline-flex items-center gap-1"><i data-lucide="star" class="h-3.5 w-3.5 fill-amber-400 text-amber-400"></i>{{ number_format($space->rating, 1) }}</span>
        </div>
        <div class="mt-auto flex items-end justify-between border-t border-slate-100 pt-4">
            <div>
                <p class="text-[11px] text-slate-400">Mulai dari</p>
                <p class="text-lg font-extrabold text-brand-600">{{ $space->formatted_price }}<span class="text-xs font-medium text-slate-400"> / {{ $space->price_unit }}</span></p>
            </div>
            <span class="rounded-lg bg-brand-50 px-3 py-2 text-xs font-bold text-brand-600 transition group-hover:bg-brand-600 group-hover:text-white">Lihat Detail</span>
        </div>
    </div>
</a>
