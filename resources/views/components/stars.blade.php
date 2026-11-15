@props(['value' => 0, 'size' => 'h-4 w-4'])
<span class="inline-flex gap-0.5 text-amber-500" role="img" aria-label="{{ number_format((float) $value, 1) }} dari 5 bintang">
    @for ($i = 1; $i <= 5; $i++)
        <svg class="{{ $size }}" viewBox="0 0 24 24" fill="{{ $i <= round((float) $value) ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.75" stroke-linejoin="round" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
    @endfor
</span>
