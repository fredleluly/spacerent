<?php

namespace Tests\Feature;

use App\Models\Space;
use App\Models\User;
use App\Support\JakartaGeo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MapTest extends TestCase
{
    use RefreshDatabase;

    private function seller(string $name = 'Sella'): User
    {
        return User::create(['name' => $name, 'email' => strtolower($name).'@x.com', 'password' => 'secret123', 'role' => 'seller']);
    }

    private function space(User $owner, string $slug, array $over = []): Space
    {
        return Space::forceCreate(array_merge([
            'name' => 'Ruang '.ucfirst($slug), 'slug' => $slug, 'category' => 'meeting-room', 'area' => 'Jakarta Selatan',
            'district' => 'Kemang', 'capacity' => 10, 'price' => 150000, 'price_unit' => 'jam', 'image' => 'https://x.test/a.jpg',
            'description' => 'Ruang nyaman', 'user_id' => $owner->id, 'latitude' => -6.2600, 'longitude' => 106.8100,
            'address_line' => 'Jl. Kemang Raya',
        ], $over));
    }

    private function payload(array $over = []): array
    {
        return array_merge([
            'name' => 'Ruang Baru', 'category' => 'meeting-room', 'area' => 'Jakarta Selatan', 'district' => 'SCBD',
            'capacity' => 8, 'price' => 150000, 'price_unit' => 'jam', 'description' => 'desc',
            'image_url' => 'https://example.com/a.jpg', 'is_available' => 1,
        ], $over);
    }

    public function test_markers_return_only_available_spaces_with_coordinates(): void
    {
        $s = $this->seller();
        $this->space($s, 'ada');
        $this->space($s, 'tanpa-koordinat', ['latitude' => null, 'longitude' => null]);
        $this->space($s, 'tutup', ['is_available' => false]);

        $res = $this->getJson(route('spaces.map'))->assertOk();
        $this->assertSame(['ada'], array_column($res->json('spaces'), 'slug'));
        $this->assertEqualsCanonicalizing(['name', 'slug', 'url', 'image', 'price', 'unit', 'capacity', 'area', 'district', 'rating', 'address', 'lat', 'lng'],
            array_intersect(['name', 'slug', 'url', 'image', 'price', 'unit', 'capacity', 'area', 'district', 'rating', 'address', 'lat', 'lng'], array_keys($res->json('spaces.0'))));
    }

    public function test_markers_sorted_by_distance_with_radius_and_validation(): void
    {
        $s = $this->seller();
        $this->space($s, 'jauh', ['latitude' => -6.1000, 'longitude' => 106.9000]);
        $this->space($s, 'dekat', ['latitude' => -6.2610, 'longitude' => 106.8110]);
        $this->space($s, 'sedang', ['latitude' => -6.2300, 'longitude' => 106.8300]);

        $res = $this->getJson(route('spaces.map', ['lat' => -6.2615, 'lng' => 106.8105]))->assertOk();
        $this->assertSame(['dekat', 'sedang', 'jauh'], array_column($res->json('spaces'), 'slug'));
        $this->assertLessThan(0.5, $res->json('spaces.0.distance_km'));

        $near = $this->getJson(route('spaces.map', ['lat' => -6.2615, 'lng' => 106.8105, 'radius_km' => 5]))->assertOk();
        $this->assertSame(['dekat', 'sedang'], array_column($near->json('spaces'), 'slug'));

        $this->getJson(route('spaces.map', ['lat' => 99, 'lng' => 106]))->assertStatus(422);
        $this->getJson(route('spaces.map', ['lat' => -6.2]))->assertStatus(422);
    }

    public function test_markers_search_by_name_district_address_and_category(): void
    {
        $s = $this->seller();
        $this->space($s, 'alpha', ['district' => 'Kemang']);
        $this->space($s, 'beta', ['district' => 'SCBD', 'address_line' => 'Menara Astra', 'category' => 'coworking']);

        $this->assertSame(['alpha'], array_column($this->getJson(route('spaces.map', ['q' => 'kemang']))->json('spaces'), 'slug'));
        $this->assertSame(['beta'], array_column($this->getJson(route('spaces.map', ['q' => 'Menara']))->json('spaces'), 'slug'));
        $this->assertSame(['beta'], array_column($this->getJson(route('spaces.map', ['category' => 'coworking']))->json('spaces'), 'slug'));
        $this->assertSame([], $this->getJson(route('spaces.map', ['q' => '100%']))->json('spaces')); // % tidak jadi wildcard
    }

    public function test_home_and_detail_pages_render_maps(): void
    {
        $s = $this->seller();
        $with = $this->space($s, 'berpeta');
        $without = $this->space($s, 'tanpa', ['latitude' => null, 'longitude' => null]);

        $this->get(route('home'))->assertOk()->assertSee('data-space-map', false)->assertSee(route('spaces.map'), false);
        $this->get(route('spaces.show', $with))->assertOk()->assertSee('data-static-map', false)->assertSee('Jl. Kemang Raya');
        $this->get(route('spaces.show', $without))->assertOk()->assertDontSee('data-static-map', false);
    }

    public function test_seller_sets_location_when_creating_and_editing(): void
    {
        $a = $this->seller('Sella');

        $this->actingAs($a)->post(route('seller.spaces.store'), $this->payload([
            'latitude' => '-6.225012', 'longitude' => '106.809044', 'address_line' => 'Jl. Jend. Sudirman, SCBD',
        ]))->assertRedirect(route('seller.spaces.index'));

        $space = Space::where('name', 'Ruang Baru')->firstOrFail();
        $this->assertEqualsWithDelta(-6.225012, $space->latitude, 0.000001);
        $this->assertEqualsWithDelta(106.809044, $space->longitude, 0.000001);
        $this->assertSame('Jl. Jend. Sudirman, SCBD', $space->address_line);
        $this->getJson(route('spaces.map'))->assertJsonPath('spaces.0.slug', $space->slug);

        $this->actingAs($a)->put(route('seller.spaces.update', $space), $this->payload(['latitude' => '-6.2700', 'longitude' => '106.8200']))->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(-6.27, $space->fresh()->latitude, 0.0001);

        // Mengosongkan lokasi dari form = ruangan hilang dari peta
        $this->actingAs($a)->put(route('seller.spaces.update', $space), $this->payload(['latitude' => '', 'longitude' => '', 'address_line' => '']))->assertSessionHasNoErrors();
        $this->assertNull($space->fresh()->latitude);
        $this->assertSame([], $this->getJson(route('spaces.map'))->json('spaces'));
    }

    public function test_invalid_coordinates_are_rejected(): void
    {
        $a = $this->seller();
        foreach ([
            ['latitude' => '106.8', 'longitude' => '-6.2'],       // tertukar
            ['latitude' => '-6.2'],                               // bujur tidak ada
            ['longitude' => '106.8'],                             // lintang tidak ada
            ['latitude' => 'abc', 'longitude' => '106.8'],
            ['latitude' => '-6.2', 'longitude' => '106.8', 'address_line' => str_repeat('x', 300)],
        ] as $bad) {
            $this->actingAs($a)->post(route('seller.spaces.store'), $this->payload($bad))->assertSessionHasErrors();
        }
        $this->assertSame(0, Space::count());
    }

    public function test_seller_form_shows_location_picker(): void
    {
        $this->actingAs($this->seller())->get(route('seller.spaces.create'))->assertOk()
            ->assertSee('data-location-picker', false)->assertSee('name="latitude"', false)->assertSee('name="address_line"', false);
    }

    public function test_jakarta_geo_is_deterministic_and_inside_jakarta(): void
    {
        [$lat, $lng] = JakartaGeo::coordinates('Kemang', 'Jakarta Selatan', 'ruang-a');
        $this->assertSame([$lat, $lng], JakartaGeo::coordinates('kemang', 'Jakarta Selatan', 'ruang-a'));
        $this->assertNotSame([$lat, $lng], JakartaGeo::coordinates('Kemang', 'Jakarta Selatan', 'ruang-b'));
        $this->assertEqualsWithDelta(-6.26, $lat, 0.01);
        $this->assertEqualsWithDelta(106.8135, $lng, 0.01);

        [$ulat, $ulng] = JakartaGeo::coordinates('Daerah Tak Dikenal', 'Jakarta Utara', 'x'); // jatuh ke titik wilayah
        $this->assertEqualsWithDelta(-6.138, $ulat, 0.01);
        $this->assertEqualsWithDelta(106.863, $ulng, 0.01);
    }
}
