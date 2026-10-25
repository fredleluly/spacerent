<?php

namespace Database\Seeders;

use App\Models\Space;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SpaceSeeder extends Seeder
{
    public function run(): void
    {
        $img = fn (string $id) => "https://images.unsplash.com/{$id}?auto=format&fit=crop&w=1200&q=80";

        $spaces = [
            [
                'name' => 'Executive Boardroom Sudirman',
                'category' => 'meeting-room', 'area' => 'Jakarta Selatan', 'district' => 'SCBD',
                'capacity' => 14, 'price' => 350000, 'price_unit' => 'jam', 'rating' => 4.9,
                'image' => $img('photo-1497366811353-6870744d04b2'),
                'description' => 'Ruang rapat eksekutif dengan pemandangan skyline SCBD, meja kayu besar, dan perangkat presentasi lengkap. Cocok untuk board meeting, presentasi klien, dan negosiasi penting.',
                'facilities' => ['Smart TV 75"', 'Video conference', 'Whiteboard', 'WiFi 500 Mbps', 'AC', 'Snack & kopi'],
            ],
            [
                'name' => 'Creatives Pod & Podcast Studio',
                'category' => 'studio-podcast', 'area' => 'Jakarta Selatan', 'district' => 'Kemang',
                'capacity' => 6, 'price' => 275000, 'price_unit' => 'jam', 'rating' => 4.8,
                'image' => $img('photo-1590650153855-d9e808231d41'),
                'description' => 'Studio podcast kedap suara dengan mic broadcast, mixer, dan kamera multi-angle. Teknisi siap membantu rekaman hingga editing dasar.',
                'facilities' => ['Mic broadcast x4', 'Audio mixer', 'Kamera 4K', 'Akustik panel', 'Teknisi', 'WiFi'],
            ],
            [
                'name' => 'Skyline Private Suite (10-12 Pax)',
                'category' => 'private-office', 'area' => 'Jakarta Pusat', 'district' => 'Thamrin',
                'capacity' => 12, 'price' => 18500000, 'price_unit' => 'bulan', 'rating' => 4.9,
                'image' => $img('photo-1497366216548-37526070297c'),
                'description' => 'Kantor privat siap pakai di jantung Thamrin lengkap dengan furnitur, pantry, dan akses 24 jam. Kontrak fleksibel mulai 3 bulan.',
                'facilities' => ['Akses 24/7', 'Furnitur lengkap', 'Pantry', 'Resepsionis', 'WiFi dedicated', 'Parkir'],
            ],
            [
                'name' => 'Open Desk Coworking Kuningan',
                'category' => 'coworking', 'area' => 'Jakarta Selatan', 'district' => 'Kuningan',
                'capacity' => 40, 'price' => 150000, 'price_unit' => 'hari', 'rating' => 4.7,
                'image' => $img('photo-1517502884422-41eaead166d4'),
                'description' => 'Hot desk di area coworking yang hidup dengan komunitas startup dan freelancer. Termasuk kopi tanpa batas dan akses ke phone booth.',
                'facilities' => ['Hot desk', 'Kopi unlimited', 'Phone booth', 'Printer', 'WiFi', 'Locker'],
            ],
            [
                'name' => 'Grand Ballroom Kelapa Gading',
                'category' => 'event-space', 'area' => 'Jakarta Utara', 'district' => 'Kelapa Gading',
                'capacity' => 300, 'price' => 25000000, 'price_unit' => 'hari', 'rating' => 4.8,
                'image' => $img('photo-1519167758481-83f550bb49b3'),
                'description' => 'Ballroom luas untuk gala dinner, pernikahan, peluncuran produk, dan konferensi. Termasuk panggung, sound system, dan lighting.',
                'facilities' => ['Panggung', 'Sound system', 'Lighting', 'Catering partner', 'Valet parking', 'Ruang rias'],
            ],
            [
                'name' => 'Workshop Hall Tebet',
                'category' => 'event-space', 'area' => 'Jakarta Selatan', 'district' => 'Tebet',
                'capacity' => 60, 'price' => 1200000, 'price_unit' => 'jam', 'rating' => 4.6,
                'image' => $img('photo-1505373877841-8d25f7d46678'),
                'description' => 'Ruang serbaguna untuk workshop, seminar, dan pelatihan dengan layout fleksibel (theater, classroom, U-shape).',
                'facilities' => ['Proyektor', 'Sound system', 'Layout fleksibel', 'Mic wireless', 'AC', 'WiFi'],
            ],
            [
                'name' => 'Virtual Office Premium Mega Kuningan',
                'category' => 'virtual-office', 'area' => 'Jakarta Selatan', 'district' => 'Mega Kuningan',
                'capacity' => 1, 'price' => 2500000, 'price_unit' => 'bulan', 'rating' => 4.7,
                'image' => $img('photo-1524758631624-e2822e304c36'),
                'description' => 'Alamat bisnis prestisius untuk legalitas perusahaan, termasuk penerimaan surat, layanan resepsionis, dan akses meeting room.',
                'facilities' => ['Alamat legal', 'Terima surat & paket', 'Resepsionis', 'Kredit meeting room', 'Nomor telepon'],
            ],
            [
                'name' => 'Huddle Room Kebayoran (4-6 Pax)',
                'category' => 'meeting-room', 'area' => 'Jakarta Selatan', 'district' => 'Kebayoran Baru',
                'capacity' => 6, 'price' => 120000, 'price_unit' => 'jam', 'rating' => 4.6,
                'image' => $img('photo-1556761175-5973dc0f32e7'),
                'description' => 'Ruang rapat kecil yang nyaman untuk diskusi tim, interview, dan sesi brainstorming singkat.',
                'facilities' => ['Smart TV', 'Whiteboard', 'WiFi', 'AC', 'Air mineral'],
            ],
            [
                'name' => 'Collab Studio Kebon Jeruk',
                'category' => 'coworking', 'area' => 'Jakarta Barat', 'district' => 'Kebon Jeruk',
                'capacity' => 25, 'price' => 2200000, 'price_unit' => 'bulan', 'rating' => 4.5,
                'image' => $img('photo-1522071820081-009f0129c71c'),
                'description' => 'Dedicated desk bulanan dengan akses 24 jam, cocok untuk tim kecil dan profesional remote.',
                'facilities' => ['Dedicated desk', 'Akses 24/7', 'Meeting room credit', 'Pantry', 'WiFi'],
            ],
        ];

        foreach ($spaces as $data) {
            Space::updateOrCreate(
                ['slug' => Str::slug($data['name'])],
                $data + ['slug' => Str::slug($data['name']), 'is_available' => true],
            );
        }
    }
}
