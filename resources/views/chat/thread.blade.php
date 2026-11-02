{{-- Params: $conversation, $messages, $mine ('customer'|'admin'), $messagesUrl, $sendUrl, $height --}}
@php $lastId = $messages->last()?->id ?? 0; @endphp
<div id="thread" class="{{ $height ?? 'h-[26rem]' }} space-y-3 overflow-y-auto bg-slate-50 p-4">
    @foreach ($messages as $m)
        <div class="flex {{ $m->sender === $mine ? 'justify-end' : 'justify-start' }}" data-id="{{ $m->id }}">
            <div class="max-w-[80%] rounded-2xl px-4 py-2 text-sm {{ $m->sender === $mine ? 'bg-brand-600 text-white' : 'border border-slate-200 bg-white text-slate-800' }}">
                <p class="whitespace-pre-line break-words">{{ $m->body }}</p>
                <p class="mt-1 text-[10px] opacity-60">{{ $m->created_at->format('d M H:i') }}</p>
            </div>
        </div>
    @endforeach
</div>
<p id="closed-note" class="{{ $conversation->status === 'closed' ? '' : 'hidden' }} bg-amber-50 px-4 py-2 text-center text-xs text-amber-700">Percakapan ditutup. Kirim pesan baru untuk membukanya kembali.</p>
<form id="send-form" class="flex gap-2 border-t border-slate-100 p-3">
    <input id="body" type="text" maxlength="1000" required autocomplete="off" placeholder="Tulis pesan..." class="flex-1 rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:border-brand-500 focus:outline-none">
    <button class="rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-brand-700">Kirim</button>
</form>
<script>
(function () {
    const thread = document.getElementById('thread');
    const form = document.getElementById('send-form');
    const input = document.getElementById('body');
    const closedNote = document.getElementById('closed-note');
    const csrf = @json(csrf_token());
    const mine = @json($mine);
    let lastId = {{ $lastId }};
    const seen = new Set(Array.from(thread.querySelectorAll('[data-id]')).map(e => e.dataset.id));

    function add(m, pending) {
        if (m.id && seen.has(String(m.id))) return;
        if (m.id) seen.add(String(m.id));
        const row = document.createElement('div');
        row.className = 'flex ' + (m.sender === mine ? 'justify-end' : 'justify-start');
        if (m.id) row.dataset.id = m.id;
        const b = document.createElement('div');
        b.className = 'max-w-[80%] rounded-2xl px-4 py-2 text-sm ' + (m.sender === mine ? 'bg-brand-600 text-white' : 'border border-slate-200 bg-white text-slate-800') + (pending ? ' opacity-60' : '');
        const p = document.createElement('p'); p.className = 'whitespace-pre-line break-words'; p.textContent = m.body;
        const t = document.createElement('p'); t.className = 'mt-1 text-[10px] opacity-60'; t.textContent = m.time || '';
        b.append(p, t); row.append(b); thread.append(row);
        thread.scrollTop = thread.scrollHeight;
        if (m.id && m.id > lastId) lastId = m.id;
        return row;
    }
    async function poll() {
        try {
            const r = await fetch(@json($messagesUrl) + '?after=' + lastId, { headers: { Accept: 'application/json' } });
            if (!r.ok) return;
            const d = await r.json();
            d.messages.forEach(m => add(m));
            closedNote.classList.toggle('hidden', d.status !== 'closed');
        } catch (e) {}
    }
    form.addEventListener('submit', async e => {
        e.preventDefault();
        const body = input.value.trim();
        if (!body) return;
        input.value = '';
        const row = add({ sender: mine, body: body, time: '' }, true);
        try {
            const r = await fetch(@json($sendUrl), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ body }),
            });
            if (!r.ok) throw new Error();
            const d = await r.json();
            row.remove();
            add(d.message);
            closedNote.classList.add('hidden');
        } catch (err) {
            row.classList.add('ring-2', 'ring-red-400');
            row.querySelector('div').lastChild.textContent = 'Gagal terkirim';
        }
    });
    thread.scrollTop = thread.scrollHeight;
    setInterval(poll, 5000);
})();
</script>
