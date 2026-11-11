<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/masuk');
    }

    public function test_theme_toggle_is_available_on_guest_and_authenticated_pages(): void
    {
        $this->get('/masuk')
            ->assertOk()
            ->assertSee('data-theme-toggle', false)
            ->assertDontSee('/bahasa/id')
            ->assertDontSee('/bahasa/en');

        $user = User::create(['username' => 'tema', 'password' => 'rahasia123']);
        $this->actingAs($user)->get('/')
            ->assertOk()
            ->assertSee('data-theme-toggle', false)
            ->assertDontSee('/bahasa/id')
            ->assertDontSee('/bahasa/en');
    }

    public function test_language_switch_translates_the_script_studio_and_persists(): void
    {
        $user = User::create(['username' => 'bahasa', 'password' => 'rahasia123']);
        $event = $user->events()->create([
            'name' => 'Seminar',
            'event_date' => '2026-12-01',
            'start_time' => '08:00',
            'duration_minutes' => 180,
            'features' => [],
        ]);
        $studioUrl = route('studio.show', [$event, 'doa']);

        $this->actingAs($user)
            ->from($studioUrl)
            ->get(route('lang', 'en'))
            ->assertRedirect($studioUrl);

        $this->get($studioUrl)
            ->assertOk()
            ->assertSee('<html lang="en">', false)
            ->assertSee('AI Script Studio')
            ->assertSee('Prayer')
            ->assertSee('Choose a religion before generating a draft.')
            ->assertSee('Select a religion')
            ->assertSee('Christian (Protestant)');

        $this->from($studioUrl)
            ->get(route('lang', 'id'))
            ->assertRedirect($studioUrl);

        $this->get($studioUrl)
            ->assertOk()
            ->assertSee('<html lang="id">', false)
            ->assertSee('Studio Naskah AI')
            ->assertSee('Pilih agama sebelum membuat draf.');
    }

    public function test_user_must_log_in_after_registering(): void
    {
        $this->post('/daftar', ['username' => 'panitia', 'password' => 'rahasia123', 'password_confirmation' => 'rahasia123'])
            ->assertRedirect('/masuk');

        $this->assertGuest();
        $this->get('/masuk')
            ->assertOk()
            ->assertSee('Akun berhasil dibuat. Silakan masuk.');

        $this->post('/masuk', ['username' => 'panitia', 'password' => 'rahasia123'])
            ->assertRedirect('/');

        $this->assertAuthenticated();
        $this->get('/')->assertOk()->assertSee('Buat Acara');
    }

    public function test_wizard_creates_event_with_rundown(): void
    {
        $user = User::create(['username' => 'u1', 'password' => 'rahasia123']);
        $this->actingAs($user)
            ->post('/acara/buat/1', ['name' => 'Seminar', 'event_date' => '2026-12-01', 'start_time' => '08:00', 'duration_hours' => 6, 'features' => ['ishoma', 'doa']])
            ->assertRedirect('/acara/buat/2');
        $this->get('/acara/buat/2')->assertOk()->assertSee('Tambah sambutan');
        $this->post('/acara/buat/2', ['speakers' => []])->assertRedirect('/acara/buat/3');
        $this->post('/acara/buat/3')->assertRedirect();

        $event = $user->events()->first();
        $this->assertTrue($event->sessions()->where('is_ishoma', true)->exists());
    }

    public function test_wizard_saves_custom_feature_and_adds_it_to_rundown(): void
    {
        $user = User::create(['username' => 'fitur', 'password' => 'rahasia123']);
        $this->actingAs($user)
            ->post('/acara/buat/1', [
                'name' => 'Seminar',
                'event_date' => '2026-12-01',
                'start_time' => '08:00',
                'duration_hours' => 3,
                'features' => [],
                'custom_features' => ['Sesi Tanya Jawab'],
            ])
            ->assertRedirect('/acara/buat/2');
        $this->post('/acara/buat/2', ['speakers' => []])->assertRedirect('/acara/buat/3');
        $this->post('/acara/buat/3')->assertRedirect();

        $event = $user->events()->firstOrFail();
        $this->assertContains('custom:Sesi Tanya Jawab', $event->features);
        $this->assertDatabaseHas('event_sessions', [
            'event_id' => $event->id,
            'title' => 'Sesi Tanya Jawab',
            'type' => 'fitur',
        ]);
    }

    public function test_ai_script_generation_uses_openrouter_chat_completions(): void
    {
        config([
            'services.openrouter.key' => 'test-openrouter-key',
            'services.openrouter.model' => 'provider/test-model',
        ]);
        $requestOptions = [];
        Http::fake(function ($request, array $options) use (&$requestOptions) {
            $requestOptions = $options;

            return Http::response([
                'choices' => [['message' => ['content' => '**Naskah # sambutan hasil AI.**']]],
            ]);
        });

        $user = User::create(['username' => 'panitia-ai', 'password' => 'rahasia123']);
        $event = $user->events()->create([
            'name' => 'Seminar',
            'event_date' => '2026-12-01',
            'start_time' => '08:00',
            'duration_minutes' => 360,
            'features' => [],
        ]);
        $event->sessions()->create([
            'position' => 1,
            'title' => 'Sambutan Ketua Panitia',
            'type' => 'sambutan',
            'start_time' => '08:10',
            'duration_minutes' => 10,
            'pic' => 'Ketua Panitia',
        ]);
        $event->sessions()->create([
            'position' => 2,
            'title' => 'Sambutan Rektor',
            'type' => 'sambutan',
            'start_time' => '08:20',
            'duration_minutes' => 10,
            'pic' => 'Rektor',
        ]);

        $this->actingAs($user)
            ->post(route('studio.generate', [$event, 'sambutan']))
            ->assertRedirect(route('studio.show', [$event, 'sambutan']));

        $this->assertDatabaseHas('scripts', [
            'event_id' => $event->id,
            'type' => 'sambutan',
            'content' => 'Naskah  sambutan hasil AI.',
            'is_ai' => true,
        ]);
        $this->assertSame(0, $requestOptions['timeout']);
        $this->assertSame(0, $requestOptions['connect_timeout']);

        Http::assertSent(fn ($request) => $request->url() === 'https://openrouter.ai/api/v1/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer test-openrouter-key')
            && $request['model'] === 'provider/test-model'
            && str_contains($request['messages'][1]['content'], 'VERSI FORMAL')
            && str_contains($request['messages'][1]['content'], 'VERSI NONFORMAL')
            && str_contains($request['messages'][1]['content'], 'tidak tegang')
            && str_contains($request['messages'][1]['content'], 'Tulis tepat 2 naskah sambutan')
            && str_contains($request['messages'][1]['content'], 'PIC: Ketua Panitia')
            && str_contains($request['messages'][1]['content'], 'PIC: Rektor'));
    }

    public function test_ai_script_generation_shows_actionable_openrouter_errors(): void
    {
        config([
            'services.openrouter.key' => 'test-openrouter-key',
            'services.openrouter.model' => 'provider/test-model',
        ]);
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::sequence()
                ->push([], 401)
                ->push([], 429),
        ]);

        $user = User::create(['username' => 'panitia-ai-errors', 'password' => 'rahasia123']);
        $event = $user->events()->create([
            'name' => 'Seminar',
            'event_date' => '2026-12-01',
            'start_time' => '08:00',
            'duration_minutes' => 360,
            'features' => [],
        ]);
        $this->actingAs($user);

        $this->post(route('studio.generate', [$event, 'sambutan']))
            ->assertSessionHas('error', 'OpenRouter tidak mengenali kredensial (HTTP 401). Pastikan OPENROUTER_API_KEY berisi API key aktif dari akun OpenRouter, bukan placeholder; setelah mengubah .env jalankan php artisan config:clear dan mulai ulang server.');
        $this->post(route('studio.generate', [$event, 'sambutan']))
            ->assertSessionHas('error', 'Kuota/kredit atau batas permintaan OpenRouter tercapai (HTTP 429). Periksa saldo dan batas akun/provider.');
    }

    public function test_ai_script_generation_shows_sanitized_openrouter_error_details(): void
    {
        config([
            'services.openrouter.key' => 'test-openrouter-key',
            'services.openrouter.model' => 'provider/test-model',
        ]);
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'error' => ['message' => 'Invalid key: sk-or-v1-do-not-show-this'],
            ], 401),
        ]);

        $user = User::create(['username' => 'panitia-ai-provider-error', 'password' => 'rahasia123']);
        $event = $user->events()->create([
            'name' => 'Seminar',
            'event_date' => '2026-12-01',
            'start_time' => '08:00',
            'duration_minutes' => 360,
            'features' => [],
        ]);

        $this->actingAs($user)
            ->post(route('studio.generate', [$event, 'sambutan']))
            ->assertSessionHas('error', function (string $message): bool {
                return str_contains($message, 'Pesan OpenRouter: Invalid key: [redacted]')
                    && ! str_contains($message, 'do-not-show-this');
            });
    }

    public function test_ai_script_generation_explains_connection_timeouts(): void
    {
        config([
            'services.openrouter.key' => 'test-openrouter-key',
            'services.openrouter.model' => 'provider/test-model',
        ]);
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::failedConnection('Connection timed out'),
        ]);

        $user = User::create(['username' => 'panitia-ai-timeout', 'password' => 'rahasia123']);
        $event = $user->events()->create([
            'name' => 'Seminar',
            'event_date' => '2026-12-01',
            'start_time' => '08:00',
            'duration_minutes' => 360,
            'features' => [],
        ]);

        $this->actingAs($user)
            ->post(route('studio.generate', [$event, 'sambutan']))
            ->assertSessionHas('error', 'OpenRouter tidak dapat dijangkau (koneksi timeout). Periksa internet, firewall/proxy, lalu coba lagi; mengganti API key tidak akan mengatasi masalah koneksi.');
    }

    public function test_sambutan_template_contains_formal_and_nonformal_versions(): void
    {
        config(['services.openrouter.key' => null]);

        $user = User::create(['username' => 'panitia-template', 'password' => 'rahasia123']);
        $event = $user->events()->create([
            'name' => 'Seminar',
            'event_date' => '2026-12-01',
            'start_time' => '08:00',
            'duration_minutes' => 360,
            'features' => [],
        ]);
        $event->sessions()->create([
            'position' => 1,
            'title' => 'Sambutan Ketua Panitia',
            'type' => 'sambutan',
            'start_time' => '08:10',
            'duration_minutes' => 10,
            'pic' => 'Ketua Panitia',
        ]);
        $event->sessions()->create([
            'position' => 2,
            'title' => 'Sambutan Rektor',
            'type' => 'sambutan',
            'start_time' => '08:20',
            'duration_minutes' => 10,
            'pic' => 'Rektor',
        ]);

        $this->actingAs($user)
            ->post(route('studio.generate', [$event, 'sambutan']))
            ->assertRedirect(route('studio.show', [$event, 'sambutan']));

        $content = $event->scripts()->where('type', 'sambutan')->value('content');
        $this->assertStringContainsString('VERSI FORMAL', $content);
        $this->assertStringContainsString('VERSI NONFORMAL', $content);
        $this->assertStringContainsString('tetap santai', $content);
        $this->assertStringContainsString('SAMBUTAN 1 — Sambutan Ketua Panitia', $content);
        $this->assertStringContainsString('SAMBUTAN 2 — Sambutan Rektor', $content);
        $this->assertStringNotContainsString('SAMBUTAN 3 —', $content);
    }

    public function test_ai_presenter_drafts_follow_presenter_sessions_in_rundown(): void
    {
        config([
            'services.openrouter.key' => 'test-openrouter-key',
            'services.openrouter.model' => 'provider/test-model',
        ]);
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'PEMATERI 1 — Topik A; PEMATERI 2 — Topik B']]],
            ]),
        ]);

        $user = User::create(['username' => 'pemateri-ai', 'password' => 'rahasia123']);
        $event = $user->events()->create([
            'name' => 'Seminar',
            'event_date' => '2026-12-01',
            'start_time' => '08:00',
            'duration_minutes' => 360,
            'features' => [],
        ]);
        $event->sessions()->createMany([
            ['position' => 1, 'title' => 'Inovasi Pendidikan', 'type' => 'sesi', 'start_time' => '08:10', 'duration_minutes' => 30, 'pic' => 'Dr. Rina'],
            ['position' => 2, 'title' => 'Sesi Tanya Jawab', 'type' => 'fitur', 'start_time' => '08:40', 'duration_minutes' => 20, 'pic' => 'Panitia'],
            ['position' => 3, 'title' => 'Masa Depan AI', 'type' => 'sesi', 'start_time' => '09:00', 'duration_minutes' => 30, 'pic' => 'Prof. Budi'],
        ]);

        $this->actingAs($user)
            ->get(route('studio.show', [$event, 'pidato']))
            ->assertOk()
            ->assertSee('Pemateri');

        $this->post(route('studio.generate', [$event, 'pidato']))
            ->assertRedirect(route('studio.show', [$event, 'pidato']));

        $this->assertDatabaseHas('scripts', [
            'event_id' => $event->id,
            'type' => 'pidato',
            'content' => 'PEMATERI 1 — Topik A; PEMATERI 2 — Topik B',
        ]);

        Http::assertSent(fn ($request) => str_contains($request['messages'][1]['content'], 'Tulis tepat 2 naskah pemateri')
            && str_contains($request['messages'][1]['content'], 'Inovasi Pendidikan | Pemateri: Dr. Rina')
            && str_contains($request['messages'][1]['content'], 'Masa Depan AI | Pemateri: Prof. Budi')
            && ! str_contains($request['messages'][1]['content'], 'Sesi Tanya Jawab'));
    }

    public function test_presenter_template_writes_formal_and_nonformal_drafts_for_each_rundown_session(): void
    {
        config(['services.openrouter.key' => null]);

        $user = User::create(['username' => 'pemateri-template', 'password' => 'rahasia123']);
        $event = $user->events()->create([
            'name' => 'Seminar',
            'event_date' => '2026-12-01',
            'start_time' => '08:00',
            'duration_minutes' => 360,
            'features' => [],
        ]);
        $event->sessions()->createMany([
            ['position' => 1, 'title' => 'Inovasi Pendidikan', 'type' => 'sesi', 'start_time' => '08:10', 'duration_minutes' => 30, 'pic' => 'Dr. Rina'],
            ['position' => 2, 'title' => 'Masa Depan AI', 'type' => 'sesi', 'start_time' => '09:00', 'duration_minutes' => 30, 'pic' => 'Prof. Budi'],
        ]);

        $this->actingAs($user)
            ->post(route('studio.generate', [$event, 'pidato']))
            ->assertRedirect(route('studio.show', [$event, 'pidato']));

        $content = $event->scripts()->where('type', 'pidato')->value('content');
        $this->assertStringContainsString('PEMATERI 1 — Inovasi Pendidikan', $content);
        $this->assertStringContainsString('PEMATERI 2 — Masa Depan AI', $content);
        $this->assertSame(2, substr_count($content, 'VERSI FORMAL'));
        $this->assertSame(2, substr_count($content, 'VERSI NONFORMAL'));
    }

    public function test_doa_studio_requires_and_shows_a_religion_selection(): void
    {
        $user = User::create(['username' => 'doa-selection', 'password' => 'rahasia123']);
        $event = $user->events()->create([
            'name' => 'Seminar',
            'event_date' => '2026-12-01',
            'start_time' => '08:00',
            'duration_minutes' => 180,
            'features' => [],
        ]);

        $this->actingAs($user)
            ->get(route('studio.show', [$event, 'doa']))
            ->assertOk()
            ->assertSee('name="religion"', false)
            ->assertSee('Pilih agama')
            ->assertSee('Islam')
            ->assertSee('Kristen (Protestan)')
            ->assertSee('Katolik')
            ->assertSee('Hindu')
            ->assertSee('Buddha')
            ->assertSee('Konghucu');

        $this->from(route('studio.show', [$event, 'doa']))
            ->post(route('studio.generate', [$event, 'doa']))
            ->assertRedirect(route('studio.show', [$event, 'doa']))
            ->assertSessionHasErrors('religion');
    }

    public function test_ai_doa_prompt_uses_selected_religion_and_requests_long_prayer(): void
    {
        config([
            'services.openrouter.key' => 'test-openrouter-key',
            'services.openrouter.model' => 'provider/test-model',
        ]);
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'Naskah doa Kristen yang panjang.']]],
            ]),
        ]);

        $user = User::create(['username' => 'doa-ai', 'password' => 'rahasia123']);
        $event = $user->events()->create([
            'name' => 'Seminar',
            'event_date' => '2026-12-01',
            'start_time' => '08:00',
            'duration_minutes' => 180,
            'features' => [],
        ]);

        $this->actingAs($user)
            ->post(route('studio.generate', [$event, 'doa']), ['religion' => 'kristen'])
            ->assertRedirect(route('studio.show', [$event, 'doa']));

        Http::assertSent(fn ($request) => $request['max_tokens'] === 3000
            && str_contains($request['messages'][1]['content'], 'sesuai agama Kristen (Protestan)')
            && str_contains($request['messages'][1]['content'], '5-7 paragraf')
            && str_contains($request['messages'][1]['content'], 'Jangan mencampur ajaran'));
    }

    public function test_doa_templates_are_long_and_match_each_selected_religion(): void
    {
        config(['services.openrouter.key' => null]);

        $user = User::create(['username' => 'doa-template', 'password' => 'rahasia123']);
        $event = $user->events()->create([
            'name' => 'Seminar',
            'event_date' => '2026-12-01',
            'start_time' => '08:00',
            'duration_minutes' => 180,
            'features' => [],
        ]);

        $expectedOpenings = [
            'islam' => 'DOA PEMBUKA ACARA — ISLAM',
            'kristen' => 'DOA PEMBUKA ACARA — KRISTEN',
            'katolik' => 'DOA PEMBUKA ACARA — KATOLIK',
            'hindu' => 'DOA PEMBUKA ACARA — HINDU',
            'buddha' => 'DOA PEMBUKA ACARA — BUDDHA',
            'konghucu' => 'DOA PEMBUKA ACARA — KONGHUCU',
        ];

        $this->actingAs($user);
        foreach ($expectedOpenings as $religion => $opening) {
            $this->post(route('studio.generate', [$event, 'doa']), ['religion' => $religion])
                ->assertRedirect(route('studio.show', [$event, 'doa']));

            $content = $event->scripts()->where('type', 'doa')->value('content');
            $this->assertStringContainsString($opening, $content);
            $this->assertGreaterThan(1000, mb_strlen($content));
        }
    }

    public function test_existing_draft_is_cleaned_in_studio_and_exports_and_pdf_is_a4(): void
    {
        $user = User::create(['username' => 'clean-export', 'password' => 'rahasia123']);
        $event = $user->events()->create([
            'name' => 'Seminar',
            'event_date' => '2026-12-01',
            'start_time' => '08:00',
            'duration_minutes' => 180,
            'features' => [],
        ]);
        $event->scripts()->create([
            'type' => 'sambutan',
            'content' => '**Naskah #lama** untuk *dibersihkan*.',
            'version' => 1,
            'is_ai' => true,
        ]);

        $this->actingAs($user)
            ->get(route('studio.show', [$event, 'sambutan']))
            ->assertOk()
            ->assertSee('Naskah lama untuk dibersihkan.')
            ->assertDontSee('**Naskah #lama**');

        $this->get(route('export.show', $event))
            ->assertOk()
            ->assertSee('Naskah lama untuk dibersihkan.')
            ->assertDontSee('**Naskah #lama**')
            ->assertSee(route('export.pdf.section', [$event, 'rundown']), false)
            ->assertSee(route('export.pdf.section', [$event, 'sambutan']), false);

        $pdf = $this->get(route('export.pdf', $event));
        $pdf->assertOk();
        $this->assertStringContainsString('application/pdf', $pdf->headers->get('content-type'));
        $this->get(route('export.pdf.section', [$event, 'rundown']))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=seminar-rundown.pdf');
        $this->get(route('export.pdf.section', [$event, 'sambutan']))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=seminar-sambutan.pdf');
        $emptyEvent = $user->events()->create([
            'name' => 'Acara Kosong',
            'event_date' => '2026-12-02',
            'start_time' => '09:00',
            'duration_minutes' => 60,
            'features' => [],
        ]);
        $this->get(route('export.pdf.section', [$emptyEvent, 'sambutan']))
            ->assertNotFound();
        $this->get(route('export.pdf.section', [$event, 'invalid']))
            ->assertNotFound();

        $pdfView = view('export.pdf', [
            'event' => $event,
            'scripts' => $event->scripts->keyBy('type'),
            'types' => \App\Models\Script::TYPES,
            'section' => 'all',
        ])->render();
        $this->assertStringContainsString('@page{size:A4;margin:30mm 30mm 30mm 40mm}', $pdfView);
        $this->assertStringContainsString('<p class="script">**Naskah #lama** untuk *dibersihkan*.</p>', $pdfView);

        $singleScriptPdfView = view('export.pdf', [
            'event' => $event,
            'scripts' => collect(),
            'types' => \App\Models\Script::TYPES,
            'section' => 'sambutan',
            'script' => $event->scripts->first(),
        ])->render();
        $this->assertStringContainsString('<p class="script">**Naskah #lama** untuk *dibersihkan*.</p>', $singleScriptPdfView);
    }
}
