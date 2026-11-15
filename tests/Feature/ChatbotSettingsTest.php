<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChatbotSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::create(['name' => ucfirst($role), 'email' => $role.'@x.com', 'password' => 'secret123', 'role' => $role]);
    }

    private function payload(array $over = []): array
    {
        return array_merge([
            'enabled' => '1', 'provider' => 'rules', 'gemini_model' => 'gemini-2.0-flash',
            'ollama_base_url' => 'http://localhost:11434', 'ollama_model' => 'llama3.1',
            'temperature' => '0.3', 'max_output_tokens' => '700', 'timeout_seconds' => '25',
            'rate_limit_per_minute' => '12', 'max_message_chars' => '500', 'history_turns' => '6',
            'welcome' => 'Halo dari tes', 'suggestions' => "Satu\nDua\n\nTiga", 'log_conversations' => '1',
        ], $over);
    }

    public function test_only_admin_can_open_or_change_settings(): void
    {
        $this->get(route('admin.chatbot.edit'))->assertRedirect();
        $this->put(route('admin.chatbot.update'), $this->payload())->assertRedirect();

        foreach (['buyer', 'seller'] as $role) {
            $this->actingAs($this->user($role));
            $this->get(route('admin.chatbot.edit'))->assertForbidden();
            $this->put(route('admin.chatbot.update'), $this->payload())->assertForbidden();
            $this->postJson(route('admin.chatbot.test'), ['provider' => 'rules'])->assertForbidden();
        }
        $this->assertSame(0, Setting::count());
    }

    public function test_admin_page_renders_without_exposing_stored_key(): void
    {
        app(SettingsService::class)->set('chatbot.gemini_api_key', 'AIzaSECRETKEY987654', true);

        $html = $this->actingAs($this->user('admin'))->get(route('admin.chatbot.edit'))->assertOk()
            ->assertSee('Chatbot AI')->assertSee('aistudio.google.com/apikey')->getContent();

        $this->assertStringContainsString('AIza******7654', $html);        // hanya versi tersamar
        $this->assertStringNotContainsString('AIzaSECRETKEY987654', $html);
    }

    public function test_update_saves_settings_and_encrypts_key(): void
    {
        $this->actingAs($this->user('admin'))
            ->put(route('admin.chatbot.update'), $this->payload(['provider' => 'gemini', 'gemini_api_key' => 'AIzaNEWKEY123456789']))
            ->assertRedirect(route('admin.chatbot.edit'))->assertSessionHas('success');

        $row = Setting::where('key', 'chatbot.gemini_api_key')->first();
        $this->assertTrue($row->is_secret);
        $this->assertStringNotContainsString('AIzaNEWKEY', $row->value);      // terenkripsi di database
        $this->assertSame('AIzaNEWKEY123456789', app(SettingsService::class)->get('chatbot.gemini_api_key'));
        $this->assertSame('gemini', app(SettingsService::class)->chatbot('provider'));
        $this->assertSame(['Satu', 'Dua', 'Tiga'], json_decode(app(SettingsService::class)->chatbot('suggestions'), true));

        $this->getJson(route('chatbot.config'))->assertJsonPath('provider', 'gemini')->assertJsonPath('welcome', 'Halo dari tes');
    }

    public function test_blank_key_keeps_existing_and_clear_removes_it(): void
    {
        $svc = app(SettingsService::class);
        $svc->set('chatbot.gemini_api_key', 'AIzaKEEPME123456', true);
        $admin = $this->user('admin');

        $this->actingAs($admin)->put(route('admin.chatbot.update'), $this->payload(['provider' => 'gemini', 'gemini_api_key' => '']))->assertSessionHasNoErrors();
        $this->assertSame('AIzaKEEPME123456', $svc->get('chatbot.gemini_api_key'));

        $this->actingAs($admin)->put(route('admin.chatbot.update'), $this->payload(['provider' => 'rules', 'clear_gemini_key' => '1']))->assertSessionHasNoErrors();
        $this->assertNull($svc->get('chatbot.gemini_api_key'));
    }

    public function test_gemini_provider_requires_a_key(): void
    {
        $this->actingAs($this->user('admin'))
            ->put(route('admin.chatbot.update'), $this->payload(['provider' => 'gemini']))
            ->assertSessionHasErrors('gemini_api_key');
        $this->assertNull(Setting::where('key', 'chatbot.provider')->first());
    }

    public function test_validation_rejects_bad_values(): void
    {
        $admin = $this->user('admin');
        foreach ([
            ['provider' => 'openai'],
            ['temperature' => '2'],
            ['max_output_tokens' => '10'],
            ['timeout_seconds' => '9999'],
            ['rate_limit_per_minute' => '0'],
            ['ollama_base_url' => 'javascript:alert(1)'],
            ['ollama_base_url' => 'ftp://host'],
            ['gemini_model' => 'bad model; rm -rf'],
        ] as $bad) {
            $this->actingAs($admin)->put(route('admin.chatbot.update'), $this->payload($bad))->assertSessionHasErrors(array_key_first($bad));
        }
    }

    public function test_disabling_hides_widget_via_config(): void
    {
        $this->actingAs($this->user('admin'))->put(route('admin.chatbot.update'), $this->payload(['enabled' => '0']));
        // checkbox tidak dikirim = mati
        $this->getJson(route('chatbot.config'))->assertJsonPath('enabled', false);
    }

    public function test_connection_test_uses_unsaved_values_and_never_returns_key(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'OK']]]]]])]);

        $res = $this->actingAs($this->user('admin'))->postJson(route('admin.chatbot.test'), [
            'provider' => 'gemini', 'gemini_api_key' => 'AIzaTRYOUT1234567', 'gemini_model' => 'gemini-2.5-flash',
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertStringNotContainsString('AIzaTRYOUT', $res->getContent());
        Http::assertSent(fn ($r) => $r->hasHeader('x-goog-api-key', 'AIzaTRYOUT1234567') && str_contains($r->url(), 'gemini-2.5-flash'));
        $this->assertNull(Setting::where('key', 'chatbot.gemini_api_key')->first()); // tes tidak menyimpan

        $this->postJson(route('admin.chatbot.test'), ['provider' => 'ollama', 'ollama_base_url' => 'file:///etc/passwd'])->assertStatus(422);
        $this->postJson(route('admin.chatbot.test'), ['provider' => 'gemini'])->assertOk()->assertJsonPath('ok', false);
    }
}
