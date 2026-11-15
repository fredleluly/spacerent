<?php

namespace Tests\Feature;

use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SdgsSectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_has_sdgs_section_even_with_empty_database(): void
    {
        $this->get(route('home'))->assertOk()
            ->assertSee('id="sdgs"', false)
            ->assertSee('Ruang yang menganggur')
            ->assertSee('Kota dan Permukiman yang Berkelanjutan')
            ->assertSee('Target resmi 8.3')
            ->assertSee('Target resmi 17.17')
            ->assertSee('data-count="0"', false);
    }

    public function test_section_shows_live_platform_numbers(): void
    {
        Cache::forget('sdgs.stats');
        $seller = User::create(['name' => 'Sella', 'email' => 's@x.com', 'password' => 'secret123', 'role' => 'seller']);
        foreach (['a' => 'Kemang', 'b' => 'SCBD', 'c' => 'SCBD'] as $slug => $district) {
            Space::forceCreate([
                'name' => 'Ruang '.$slug, 'slug' => $slug, 'category' => 'meeting-room', 'area' => 'Jakarta Selatan', 'district' => $district,
                'capacity' => 8, 'price' => 100000, 'price_unit' => 'jam', 'image' => 'x.jpg', 'description' => 'd', 'user_id' => $seller->id,
                'latitude' => $slug === 'c' ? null : -6.2, 'longitude' => $slug === 'c' ? null : 106.8,
            ]);
        }

        $html = $this->get(route('home'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/data-count="3">3</', $html);   // ruangan aktif
        $this->assertMatchesRegularExpression('/data-count="1">1</', $html);   // mitra pemilik
        $this->assertMatchesRegularExpression('/data-count="2">2</', $html);   // kawasan unik dan ruangan di peta
    }

    public function test_section_is_honest_about_claims_and_sources(): void
    {
        $this->get(route('home'))->assertSee('bukan klaim capaian terukur')
            ->assertSee('sdgs.un.org/goals')->assertSee('Bappenas dan JUTPI II');
    }
}
