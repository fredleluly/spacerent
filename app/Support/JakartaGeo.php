<?php

namespace App\Support;

/**
 * Koordinat pusat kawasan di Jakarta (untuk seeder dan pengisian koordinat ruangan lama).
 * Titik diberi pergeseran kecil yang deterministik per ruangan (maks sekitar 200 m) agar penanda tidak bertumpuk.
 */
class JakartaGeo
{
    /** @var array<string, array{0: float, 1: float}> kunci huruf kecil */
    private const DISTRICTS = [
        'scbd' => [-6.2250, 106.8090], 'sudirman' => [-6.2088, 106.8190], 'senayan' => [-6.2250, 106.8010],
        'kemang' => [-6.2600, 106.8135], 'thamrin' => [-6.1950, 106.8230], 'kuningan' => [-6.2300, 106.8300],
        'mega kuningan' => [-6.2290, 106.8340], 'setiabudi' => [-6.2150, 106.8300], 'gatot subroto' => [-6.2300, 106.8200],
        'kelapa gading' => [-6.1590, 106.9070], 'tebet' => [-6.2260, 106.8540], 'kebayoran baru' => [-6.2440, 106.7990],
        'blok m' => [-6.2440, 106.8000], 'kebon jeruk' => [-6.1920, 106.7690], 'menteng' => [-6.1950, 106.8330],
        'cikini' => [-6.1890, 106.8410], 'gambir' => [-6.1760, 106.8280], 'tanah abang' => [-6.1860, 106.8110],
        'pondok indah' => [-6.2650, 106.7840], 'cilandak' => [-6.2900, 106.8000], 'pluit' => [-6.1260, 106.7900],
        'kemayoran' => [-6.1620, 106.8480], 'grogol' => [-6.1670, 106.7900], 'cawang' => [-6.2430, 106.8700],
        'rawamangun' => [-6.1950, 106.8700], 'pulo gadung' => [-6.1860, 106.9080], 'duren sawit' => [-6.2330, 106.9100],
        'tanjung priok' => [-6.1250, 106.8800], 'ancol' => [-6.1260, 106.8420], 'cengkareng' => [-6.1480, 106.7370],
    ];

    /** Titik tengah wilayah bila kawasan tidak dikenal. */
    private const AREAS = [
        'jakarta pusat' => [-6.1865, 106.8340], 'jakarta selatan' => [-6.2615, 106.8106], 'jakarta barat' => [-6.1680, 106.7590],
        'jakarta utara' => [-6.1380, 106.8630], 'jakarta timur' => [-6.2250, 106.9004],
    ];

    private const CENTER = [-6.2088, 106.8456];

    /** @return array{0: float, 1: float} [lat, lng] */
    public static function coordinates(string $district, string $area, string $seed): array
    {
        $key = mb_strtolower(trim($district));
        [$lat, $lng] = self::DISTRICTS[$key] ?? self::AREAS[mb_strtolower(trim($area))] ?? self::CENTER;

        // Pergeseran deterministik dari hash seed: -0.0018..+0.0018 derajat.
        $h = crc32($seed);
        $dLat = ((($h & 0xFFFF) / 0xFFFF) - 0.5) * 0.0036;
        $dLng = (((($h >> 16) & 0xFFFF) / 0xFFFF) - 0.5) * 0.0036;

        return [round($lat + $dLat, 6), round($lng + $dLng, 6)];
    }

    public static function address(string $district, string $area): string
    {
        return trim($district.', '.$area);
    }
}
