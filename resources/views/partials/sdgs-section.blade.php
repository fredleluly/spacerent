{{-- Section SDGs di landing page. Kartu persegi bergaya SDG dengan warna resmi, datar tanpa gradien.
     Gaya di resources/css/sdgs.css, perilaku di resources/js/sdgs.js. Lencana dan piktogram dibuat sendiri (bukan logo resmi PBB). --}}
@php
    $stats = \Illuminate\Support\Facades\Cache::remember('sdgs.stats', 600, function () {
        $base = \App\Models\Space::query()->where('is_available', true);

        return [
            'spaces' => (clone $base)->count(),
            'owners' => (clone $base)->whereNotNull('user_id')->distinct()->count('user_id'),
            'districts' => (clone $base)->distinct()->count('district'),
            'mapped' => (clone $base)->withCoordinates()->count(),
        ];
    });
    $commission = rtrim(rtrim(number_format((float) config('spacerent.commission_percent'), 1, ',', ''), '0'), ',');

    // Warna resmi 17 goal (sdgs.un.org). Goal yang didukung SpaceRent diberi kartu penuh.
    $palette = [1 => '#E5243B', 2 => '#DDA63A', 3 => '#4C9F38', 4 => '#C5192D', 5 => '#FF3A21', 6 => '#26BDE2', 7 => '#FCC30B', 8 => '#A21942', 9 => '#FD6925',
        10 => '#DD1367', 11 => '#FD9D24', 12 => '#BF8B2E', 13 => '#3F7E44', 14 => '#0A97D9', 15 => '#56C02B', 16 => '#00689D', 17 => '#19486A'];

    // 'on' = warna teks di atas warna goal (hitam di warna terang agar kontras AA).
    $goals = [
        11 => ['on' => '#111111', 'name' => 'Kota dan Permukiman yang Berkelanjutan', 'target' => '11.3', 'feature' => 'Peta dan pencarian terdekat',
            'text' => 'Jakarta tidak kekurangan bangunan, ia kekurangan pemanfaatan. Dengan memetakan ruang yang sudah ada dan mendekatkannya ke penggunanya, SpaceRent mendukung kota yang efisien dan mengurangi perjalanan yang tidak perlu.',
            'official' => 'Meningkatkan urbanisasi yang inklusif dan berkelanjutan, serta kapasitas perencanaan permukiman yang partisipatif dan terpadu.'],
        8 => ['on' => '#FFFFFF', 'name' => 'Pekerjaan Layak dan Pertumbuhan Ekonomi', 'target' => '8.3', 'feature' => 'Payout penjual, komisi '.$commission.'%',
            'text' => 'Pemilik ruang kecil, pengelola coworking, dan UMKM memperoleh penghasilan tambahan dari ruang yang tadinya menganggur, dengan pembayaran online dan komisi yang transparan.',
            'official' => 'Mendorong kegiatan produktif, kewirausahaan, dan pertumbuhan usaha mikro, kecil, dan menengah.'],
        9 => ['on' => '#111111', 'name' => 'Industri, Inovasi, dan Infrastruktur', 'target' => '9.3', 'feature' => 'Jadwal real-time dan asisten AI',
            'text' => 'Jadwal real-time, pembayaran digital, peta, dan asisten AI memberi usaha kecil infrastruktur digital yang biasanya hanya dimiliki jaringan besar.',
            'official' => 'Meningkatkan akses usaha skala kecil ke layanan keuangan dan memadukannya ke dalam rantai nilai serta pasar.'],
        12 => ['on' => '#111111', 'name' => 'Konsumsi dan Produksi yang Bertanggung Jawab', 'target' => '12.2', 'feature' => 'Sewa per jam',
            'text' => 'Satu ruang dipakai bergantian oleh banyak tim, sehingga kebutuhan yang hanya beberapa jam tidak perlu membangun kantor baru.',
            'official' => 'Mencapai pengelolaan berkelanjutan dan penggunaan sumber daya alam yang efisien.'],
        13 => ['on' => '#FFFFFF', 'name' => 'Penanganan Perubahan Iklim', 'target' => '13.2', 'feature' => 'Pencarian terdekat',
            'text' => 'Ruang kerja yang lebih dekat dari rumah atau lokasi rapat berpotensi memangkas perjalanan jauh dan emisinya. Ini arah desain, belum klaim terukur.',
            'official' => 'Mengintegrasikan langkah penanganan perubahan iklim ke dalam kebijakan, strategi, dan perencanaan.'],
        17 => ['on' => '#FFFFFF', 'name' => 'Kemitraan untuk Mencapai Tujuan', 'target' => '17.17', 'feature' => 'Data terbuka OpenStreetMap',
            'text' => 'SpaceRent dibangun bersama mitra pemilik ruang, penyedia pembayaran, dan data terbuka OpenStreetMap. Kolaborasi, bukan kepemilikan tunggal.',
            'official' => 'Mendorong kemitraan publik, publik-swasta, dan masyarakat sipil yang efektif.'],
    ];

    // Piktogram garis sederhana (viewBox 64), digambar sendiri.
    $icons = [
        8 => '<path d="M8 52h48"/><path d="M14 44V34M26 44V26M38 44V30M50 44V16"/><path d="m36 12 14 0 0 14" /><path d="M14 30 32 14l8 6 10-10"/>',
        9 => '<circle cx="32" cy="32" r="9"/><path d="M32 8v8M32 48v8M8 32h8M48 32h8M15 15l6 6M43 43l6 6M49 15l-6 6M21 43l-6 6"/>',
        11 => '<path d="M6 54h52"/><path d="M12 54V26h14v28M26 54V12h14v42M40 54V30h12v24"/><path d="M31 20h4M31 28h4M31 36h4M17 34h4M17 42h4M45 38h2M45 46h2"/>',
        12 => '<path d="M44 20a16 16 0 0 0-28 4"/><path d="m14 14 2 10 10-2"/><path d="M20 44a16 16 0 0 0 28-4"/><path d="m50 50-2-10-10 2"/>',
        13 => '<circle cx="32" cy="32" r="20"/><path d="M12 32h40M32 12c-8 8-8 32 0 40M32 12c8 8 8 32 0 40"/>',
        17 => '<circle cx="32" cy="14" r="7"/><circle cx="14" cy="46" r="7"/><circle cx="50" cy="46" r="7"/><path d="m28 20-11 20M36 20l11 20M21 46h22"/>',
    ];
@endphp

<section id="sdgs" class="sdg" data-sdgs aria-labelledby="sdgs-title">
    {{-- Pita 17 warna resmi SDG --}}
    <div class="sdg__bar" aria-hidden="true">@foreach ($palette as $c)<i style="background:{{ $c }}"></i>@endforeach</div>

    <div class="container-page sdg__wrap">
        <header class="sdg__head">
            <div>
                <p class="sdg__eyebrow">Tujuan Pembangunan Berkelanjutan</p>
                <h2 id="sdgs-title" class="sdg__title">Ruang yang menganggur jadi dampak nyata.</h2>
                <p class="sdg__lead">SpaceRent mengubah ruang kosong di Jakarta menjadi tempat kerja bersama. Pemilik memperoleh penghasilan, pekerja mendapat tempat yang dekat, kota memakai bangunan yang sudah ada.</p>
            </div>

            {{-- 17 goal: yang didukung berwarna, selebihnya abu-abu --}}
            <div>
                <ol class="sdg__strip" aria-label="17 tujuan SDGs. SpaceRent mendukung 6: nomor 8, 9, 11, 12, 13, dan 17">
                    @foreach ($palette as $n => $c)
                        @if (isset($goals[$n]))
                            <li><a href="#sdg-{{ $n }}" class="is-on" style="--c:{{ $c }};--on:{{ $goals[$n]['on'] }}" data-pop aria-label="Goal {{ $n }}: {{ $goals[$n]['name'] }}">{{ $n }}</a></li>
                        @else
                            <li><span aria-hidden="true">{{ $n }}</span></li>
                        @endif
                    @endforeach
                </ol>
                <p class="sdg__cap"><strong>6 dari 17</strong> tujuan yang kami dukung lewat fitur nyata di platform.</p>
            </div>
        </header>

        {{-- Alur dampak --}}
        <ol class="sdg__flow" aria-label="Alur dampak SpaceRent">
            <li data-reveal><b>1</b><span><strong>Ruang menganggur</strong>Ruang rapat, studio, dan kantor yang kosong di luar jam pakai pemiliknya.</span></li>
            <li data-reveal style="--d:1"><b>2</b><span><strong>Dipakai bergantian</strong>Jadwal real-time dan peta membantu tim menemukan ruang terdekat untuk beberapa jam saja.</span></li>
            <li data-reveal style="--d:2"><b>3</b><span><strong>Penghasilan dan kota lebih ringan</strong>Pemilik memperoleh pendapatan, perjalanan jauh berkurang, tidak perlu gedung baru.</span></li>
        </ol>

        {{-- Kartu SDG --}}
        <div class="sdg__tiles">
            @foreach ($goals as $n => $g)
                <article id="sdg-{{ $n }}" class="sdgt {{ $n === 11 ? 'sdgt--main' : '' }}" style="--c:{{ $palette[$n] }};--on:{{ $g['on'] }};--d:{{ $loop->index }}" data-reveal data-tile tabindex="0" aria-label="Goal {{ $n }}: {{ $g['name'] }}">
                    <div class="sdgt__face">
                        <span class="sdgt__num">{{ $n }}</span>
                        <h3 class="sdgt__name">{{ $g['name'] }}</h3>
                        <svg class="sdgt__icon" viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icons[$n] !!}</svg>
                        @if ($n !== 11)<span class="sdgt__hint" aria-hidden="true">Detail <i>+</i></span>@endif
                    </div>
                    <div class="sdgt__body">
                        <p class="sdgt__label">Cara SpaceRent berkontribusi</p>
                        <p>{{ $g['text'] }}</p>
                        <p class="sdgt__label">Target resmi {{ $g['target'] }}</p>
                        <p class="sdgt__official">{{ $g['official'] }}</p>
                        <p class="sdgt__feat">Fitur: {{ $g['feature'] }}</p>
                        @if ($n === 11)
                            <p class="sdgt__fact"><strong>Rp <span data-count="100">100</span> triliun</strong> per tahun: perkiraan kerugian kemacetan Jabodetabek (studi Bappenas dan JUTPI II, 2019). Ruang kerja yang lebih dekat ikut memangkas perjalanan yang tidak perlu.</p>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>

        {{-- Angka platform --}}
        <dl class="sdg__stats" aria-label="Angka platform saat ini">
            @foreach ([[$stats['spaces'], 'ruangan aktif'], [$stats['owners'], 'mitra pemilik'], [$stats['districts'], 'kawasan terjangkau'], [$stats['mapped'], 'ruangan di peta']] as $i => [$n, $label])
                <div data-reveal style="--d:{{ $i }}"><dd><span data-count="{{ $n }}">{{ $n }}</span></dd><dt>{{ $label }}</dt></div>
            @endforeach
        </dl>

        <div class="sdg__cta" data-reveal>
            <div>
                <h3>Ikut jadi bagian dari perubahannya.</h3>
                <p>Pakai ruang yang sudah ada, atau buka ruangmu untuk orang lain.</p>
            </div>
            <div class="sdg__btns">
                <a href="#peta" class="btn btn-lg btn-dark">Cari ruangan terdekat</a>
                <a href="{{ route('register', ['role' => 'seller']) }}" class="btn btn-lg btn-outline">Daftarkan ruangan</a>
            </div>
        </div>

        <p class="sdg__src">
            Pemetaan ke SDGs adalah arah dan desain platform, bukan klaim capaian terukur. Warna dan nomor goal mengikuti
            <a href="https://sdgs.un.org/goals" target="_blank" rel="noopener">sdgs.un.org/goals</a>.
            Angka kerugian kemacetan: studi Bappenas dan JUTPI II (2019) sebagaimana dikutip
            <a href="https://www.thejakartapost.com/opinion/2025/09/27/jakarta-digs-deep" target="_blank" rel="noopener">The Jakarta Post</a>.
            Kartu dan piktogram dibuat sendiri dengan warna resmi, bukan logo atau ikon resmi PBB.
        </p>
    </div>
</section>
