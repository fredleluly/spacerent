@extends('admin.layout')

@section('title', 'Chatbot AI')
@section('heading', 'Chatbot AI')

@section('content')
@php
    $old = fn ($k) => old($k, $v[$k] ?? '');
    $providers = [
        'rules' => ['Mesin aturan', 'Tanpa AI eksternal. Gratis, cepat, selalu aktif. Membaca database langsung.'],
        'gemini' => ['Google Gemini', 'AI cloud (Flash gratis di Google AI Studio). Paham bahasa bebas.'],
        'ollama' => ['Ollama (lokal)', 'AI di komputer/server sendiri. Data tidak keluar jaringan.'],
    ];
    $selected = $old('provider');
@endphp

<form method="POST" action="{{ route('admin.chatbot.update') }}" id="chatbot-form" class="grid max-w-5xl gap-6" autocomplete="off">
    @csrf @method('PUT')

    {{-- Status --}}
    <section class="card grid gap-4 p-5 sm:grid-cols-4">
        <div class="sm:col-span-2">
            <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Provider aktif</p>
            <p class="mt-1 flex flex-wrap items-center gap-2 text-lg font-semibold">
                {{ $providers[$effective][0] }}
                @if ($effective !== $v['provider'])<span class="badge badge-warn">Kunci belum diisi, memakai mesin aturan</span>@endif
                @unless ($v['enabled'])<span class="badge badge-danger">Dimatikan</span>@endunless
            </p>
            <p class="help">Bila provider AI gagal, chatbot otomatis memakai mesin aturan agar pengunjung tetap terlayani.</p>
        </div>
        <div><p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Percakapan 24 jam</p><p class="mt-1 text-2xl font-semibold">{{ $stats['total24'] }}</p><p class="help">{{ $stats['fallback24'] }} memakai cadangan</p></div>
        <div><p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Rata-rata respons</p><p class="mt-1 text-2xl font-semibold">{{ $stats['avg_ms'] }} <span class="text-sm font-medium text-zinc-500">ms</span></p></div>
    </section>

    {{-- Provider --}}
    <section class="card p-5">
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-base font-semibold">Penyedia AI</h2>
            <label class="inline-flex cursor-pointer items-center gap-2 text-sm font-medium">
                <input type="checkbox" name="enabled" value="1" @checked($old('enabled')) class="h-4 w-4 rounded border-zinc-300 text-brand-600"> Chatbot aktif di situs
            </label>
        </div>

        <div class="mt-4 grid gap-3 md:grid-cols-3" role="radiogroup" aria-label="Penyedia AI">
            @foreach ($providers as $key => [$name, $desc])
                <label class="relative flex cursor-pointer flex-col gap-1 rounded-2xl border p-4 transition has-[:checked]:border-brand-600 has-[:checked]:bg-brand-50 has-[:checked]:ring-2 has-[:checked]:ring-brand-600/20 {{ $selected === $key ? 'border-brand-600' : 'border-zinc-200 hover:border-zinc-400' }}">
                    <input type="radio" name="provider" value="{{ $key }}" @checked($selected === $key) class="absolute right-4 top-4 h-4 w-4 text-brand-600" data-provider>
                    <span class="font-semibold">{{ $name }}</span>
                    <span class="text-xs leading-relaxed text-zinc-600">{{ $desc }}</span>
                </label>
            @endforeach
        </div>
        @error('provider')<p class="error">{{ $message }}</p>@enderror

        {{-- Gemini --}}
        <div data-panel="gemini" class="mt-5 grid gap-4 rounded-2xl bg-zinc-50 p-4 md:grid-cols-2">
            <div class="md:col-span-2">
                <label class="label" for="gemini_api_key">Kunci API Gemini</label>
                <input id="gemini_api_key" type="password" name="gemini_api_key" value="" class="input" autocomplete="new-password" spellcheck="false"
                       placeholder="{{ $keyMask ? 'Tersimpan: '.$keyMask.' (kosongkan bila tidak diganti)' : 'Tempel kunci dari Google AI Studio (AIza...)' }}" aria-invalid="{{ $errors->has('gemini_api_key') ? 'true' : 'false' }}">
                @error('gemini_api_key')<p class="error">{{ $message }}</p>@enderror
                <p class="help">Dapatkan gratis di <a href="https://aistudio.google.com/apikey" target="_blank" rel="noopener" class="font-semibold text-brand-700 underline">aistudio.google.com/apikey</a>. Kunci disimpan terenkripsi, tidak pernah ditampilkan ulang, dan tidak dikirim ke browser pengunjung.</p>
                @if ($keyMask)
                    <label class="mt-2 inline-flex items-center gap-2 text-sm text-rose-700"><input type="checkbox" name="clear_gemini_key" value="1" class="h-4 w-4 rounded border-zinc-300"> Hapus kunci tersimpan</label>
                @endif
            </div>
            <div>
                <label class="label" for="gemini_model">Model</label>
                <input id="gemini_model" name="gemini_model" value="{{ $old('gemini_model') }}" list="gemini-models" class="input">
                <datalist id="gemini-models"><option value="gemini-2.0-flash"><option value="gemini-2.5-flash"><option value="gemini-2.5-flash-lite"><option value="gemini-1.5-flash"></datalist>
                @error('gemini_model')<p class="error">{{ $message }}</p>@enderror
                <p class="help">Pilih model "Flash" untuk respons cepat dan kuota gratis.</p>
            </div>
        </div>

        {{-- Ollama --}}
        <div data-panel="ollama" class="mt-5 grid gap-4 rounded-2xl bg-zinc-50 p-4 md:grid-cols-2">
            <div>
                <label class="label" for="ollama_base_url">Alamat server Ollama</label>
                <input id="ollama_base_url" type="url" name="ollama_base_url" value="{{ $old('ollama_base_url') }}" class="input" placeholder="http://localhost:11434">
                @error('ollama_base_url')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label" for="ollama_model">Model</label>
                <input id="ollama_model" name="ollama_model" value="{{ $old('ollama_model') }}" list="ollama-models" class="input">
                <datalist id="ollama-models">@foreach ($ollamaModels as $m)<option value="{{ $m }}">@endforeach</datalist>
                @error('ollama_model')<p class="error">{{ $message }}</p>@enderror
                <p class="help">@if ($ollamaModels) Terpasang: {{ implode(', ', $ollamaModels) }}. @endif Pakai model yang mendukung tool, mis. llama3.1, qwen2.5, mistral-nemo.</p>
            </div>
            <p class="help md:col-span-2">Jalankan <code class="rounded bg-zinc-200 px-1">ollama serve</code> lalu <code class="rounded bg-zinc-200 px-1">ollama pull llama3.1</code>. Bila Laravel berjalan di server lain, ubah alamatnya dan pastikan port 11434 dapat dijangkau.</p>
        </div>

        <div data-panel="rules" class="mt-5 rounded-2xl bg-zinc-50 p-4 text-sm text-zinc-700">
            Mesin aturan memahami cari ruangan, terdekat dari lokasi, jadwal kosong, kebijakan batal, cara pesan, dan pesanan pengguna yang login. Cocok sebagai cadangan atau bila belum punya kunci AI.
        </div>

        {{-- Uji koneksi --}}
        <div class="mt-5 flex flex-wrap items-center gap-3">
            <button type="button" id="test-btn" class="btn btn-outline"><i data-lucide="zap" class="h-4 w-4"></i> Tes koneksi</button>
            <p id="test-result" class="text-sm" role="status" aria-live="polite"></p>
        </div>
        <p class="help">Tes memakai isian di atas (belum perlu disimpan). Kunci yang dikosongkan memakai kunci yang tersimpan.</p>
    </section>

    {{-- Perilaku --}}
    <section class="card grid gap-4 p-5 md:grid-cols-3">
        <h2 class="text-base font-semibold md:col-span-3">Parameter dan batas</h2>
        @foreach ([
            ['temperature', 'Temperature (0-1)', 'Rendah = konsisten dan faktual. Disarankan 0,2-0,4.', 'step="0.1" min="0" max="1"'],
            ['max_output_tokens', 'Maks token jawaban', 'Batas panjang jawaban AI (64-4096).', 'min="64" max="4096"'],
            ['timeout_seconds', 'Timeout (detik)', 'Lewat batas ini chatbot memakai mesin aturan.', 'min="3" max="120"'],
            ['rate_limit_per_minute', 'Batas pesan per menit', 'Per akun atau per alamat IP. Mencegah penyalahgunaan kuota.', 'min="1" max="120"'],
            ['max_message_chars', 'Maks karakter pesan', 'Pesan lebih panjang ditolak (50-2000).', 'min="50" max="2000"'],
            ['history_turns', 'Giliran riwayat dikirim', 'Konteks percakapan ke model. 0 = tanpa riwayat.', 'min="0" max="20"'],
        ] as [$name, $label, $help, $attrs])
            <div>
                <label class="label" for="{{ $name }}">{{ $label }}</label>
                <input id="{{ $name }}" type="number" name="{{ $name }}" value="{{ $old($name) }}" class="input" {!! $attrs !!} required>
                @error($name)<p class="error">{{ $message }}</p>@enderror
                <p class="help">{{ $help }}</p>
            </div>
        @endforeach
    </section>

    {{-- Pesan --}}
    <section class="card grid gap-4 p-5 md:grid-cols-2">
        <h2 class="text-base font-semibold md:col-span-2">Pesan di widget</h2>
        <div>
            <label class="label" for="welcome">Pesan sambutan</label>
            <textarea id="welcome" name="welcome" rows="4" maxlength="400" class="input">{{ $old('welcome') }}</textarea>
            @error('welcome')<p class="error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="label" for="suggestions">Saran pertanyaan (satu per baris, maks 6)</label>
            <textarea id="suggestions" name="suggestions" rows="4" class="input">{{ $old('suggestions') }}</textarea>
            @error('suggestions')<p class="error">{{ $message }}</p>@enderror
        </div>
        <label class="inline-flex items-center gap-2 text-sm font-medium md:col-span-2">
            <input type="checkbox" name="log_conversations" value="1" @checked($old('log_conversations')) class="h-4 w-4 rounded border-zinc-300 text-brand-600">
            Simpan log percakapan (pertanyaan, jawaban, provider, durasi). Lokasi dan kunci tidak pernah disimpan.
        </label>
    </section>

    {{-- System prompt --}}
    <section class="card p-5">
        <details @if (old('system_prompt', $v['system_prompt'])) open @endif>
            <summary class="cursor-pointer text-base font-semibold">System prompt (lanjutan)</summary>
            <p class="help mt-2">Kosongkan untuk memakai prompt bawaan di <code>resources/chatbot/system-prompt.md</code>. Aturan akses data tetap dijaga server walau prompt diubah. Konteks tanggal, peran pengguna, dan tool ditambahkan otomatis.</p>
            <textarea name="system_prompt" rows="10" maxlength="8000" class="input mt-3 font-mono text-xs" placeholder="Kosong = pakai bawaan">{{ old('system_prompt', $v['system_prompt']) }}</textarea>
            @error('system_prompt')<p class="error">{{ $message }}</p>@enderror
            <label class="mt-2 inline-flex items-center gap-2 text-sm"><input type="checkbox" name="reset_prompt" value="1" class="h-4 w-4 rounded border-zinc-300"> Kembalikan ke bawaan saat menyimpan</label>
            <details class="mt-3"><summary class="cursor-pointer text-sm font-medium text-zinc-700">Lihat prompt bawaan</summary><pre class="mt-2 max-h-72 overflow-auto whitespace-pre-wrap rounded-xl bg-zinc-50 p-3 text-xs">{{ $defaultPrompt }}</pre></details>
        </details>
    </section>

    <div class="flex items-center gap-3"><button class="btn btn-primary btn-lg">Simpan pengaturan</button><a href="{{ route('home') }}" target="_blank" class="btn btn-outline btn-lg">Coba di situs</a></div>
</form>

{{-- Log --}}
<section class="card mt-8 max-w-5xl overflow-hidden">
    <h2 class="border-b border-zinc-200 px-5 py-3 text-base font-semibold">20 percakapan terakhir</h2>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500"><tr><th class="px-4 py-2">Waktu</th><th class="px-4 py-2">Pertanyaan</th><th class="px-4 py-2">Provider</th><th class="px-4 py-2">Tool</th><th class="px-4 py-2 text-right">ms</th></tr></thead>
            <tbody class="divide-y divide-zinc-100">
                @forelse ($logs as $log)
                    <tr>
                        <td class="whitespace-nowrap px-4 py-2 text-zinc-500">{{ $log->created_at?->format('d M H:i') }}</td>
                        <td class="max-w-xs truncate px-4 py-2" title="{{ $log->question }}">{{ \Illuminate\Support\Str::limit($log->question, 80) }}</td>
                        <td class="px-4 py-2"><span class="badge {{ $log->is_error ? 'badge-warn' : 'badge-neutral' }}">{{ $log->provider }}{{ $log->is_error ? ' (cadangan)' : '' }}</span></td>
                        <td class="px-4 py-2 text-xs text-zinc-500">{{ implode(', ', $log->tools ?? []) ?: '-' }}</td>
                        <td class="px-4 py-2 text-right text-zinc-500">{{ $log->latency_ms }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-zinc-500">Belum ada percakapan tercatat.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection

@push('scripts')
<script>
(() => {
    const form = document.getElementById('chatbot-form');
    const panels = form.querySelectorAll('[data-panel]');
    const radios = form.querySelectorAll('[data-provider]');
    const sync = () => {
        const cur = form.querySelector('[data-provider]:checked')?.value;
        panels.forEach((p) => { p.hidden = p.dataset.panel !== cur; });
    };
    radios.forEach((r) => r.addEventListener('change', sync));
    sync();

    const btn = document.getElementById('test-btn');
    const out = document.getElementById('test-result');
    btn.addEventListener('click', async () => {
        const fd = new FormData(form);
        const body = {};
        ['provider', 'gemini_api_key', 'gemini_model', 'ollama_base_url', 'ollama_model'].forEach((k) => { body[k] = fd.get(k) || ''; });
        btn.disabled = true; out.className = 'text-sm text-zinc-600'; out.textContent = 'Menguji koneksi...';
        try {
            const res = await fetch(@json(route('admin.chatbot.test')), {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) },
                body: JSON.stringify(body),
            });
            const d = await res.json();
            if (res.status === 422) { out.className = 'text-sm text-rose-700'; out.textContent = Object.values(d.errors || {})[0]?.[0] || 'Isian tidak valid.'; }
            else { out.className = 'text-sm font-medium ' + (d.ok ? 'text-emerald-700' : 'text-rose-700'); out.textContent = (d.ok ? 'Berhasil' : 'Gagal') + (d.latency_ms ? ' (' + d.latency_ms + ' ms)' : '') + ': ' + d.message; }
        } catch (e) { out.className = 'text-sm text-rose-700'; out.textContent = 'Tidak dapat menguji. Periksa koneksi.'; }
        btn.disabled = false;
    });
})();
</script>
@endpush
