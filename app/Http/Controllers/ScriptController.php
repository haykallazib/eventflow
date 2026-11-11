<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Script;
use App\Services\ScriptGenerator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class ScriptController extends Controller
{
    public function show(Event $event, ScriptGenerator $ai, string $tab = 'sambutan'): View
    {
        Gate::authorize('manage', $event);
        abort_unless(array_key_exists($tab, Script::TYPES), 404);
        $script = $event->scripts()->where('type', $tab)->first();

        return view('studio.show', [
            'event' => $event->load('sessions', 'speakers'),
            'tab' => $tab,
            'script' => $script,
            'scriptContent' => Script::cleanDraftContent($script?->content ?? ''),
            'usesAi' => $ai->usesAi(),
            'prayerReligions' => config('eventflow.prayer_religions'),
        ]);
    }

    public function generate(Request $request, Event $event, ScriptGenerator $ai, string $tab): RedirectResponse
    {
        Gate::authorize('manage', $event);
        abort_unless(array_key_exists($tab, Script::TYPES), 404);
        $data = $tab === 'doa'
            ? $request->validate([
                'religion' => ['required', 'string', \Illuminate\Validation\Rule::in(array_keys(config('eventflow.prayer_religions')))],
            ], [
                'religion.required' => 'Pilih agama untuk membuat naskah doa.',
                'religion.in' => 'Pilihan agama tidak tersedia.',
            ])
            : [];
        $event->load('sessions', 'speakers');

        try {
            $text = $ai->generate($event, $tab, $data['religion'] ?? null);
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('studio.show', [$event, $tab])
                ->with('error', $this->generationErrorMessage($e));
        }

        $script = $event->scripts()->where('type', $tab)->first();
        $event->scripts()->updateOrCreate(
            ['type' => $tab],
            ['content' => $text, 'is_ai' => true, 'version' => ($script?->version ?? 0) + 1],
        );

        return redirect()->route('studio.show', [$event, $tab])->with('status', 'Naskah berhasil dibuat.');
    }

    private function generationErrorMessage(Throwable $exception): string
    {
        if ($exception instanceof ConnectionException) {
            return 'OpenRouter tidak dapat dijangkau (koneksi timeout). Periksa internet, firewall/proxy, lalu coba lagi; mengganti API key tidak akan mengatasi masalah koneksi.';
        }

        if (! $exception instanceof RuntimeException
            || ! preg_match('/Layanan AI tidak merespons \((\d{3})\)\./', $exception->getMessage(), $matches)) {
            return 'Naskah gagal dibuat. Periksa koneksi atau kunci API, lalu coba lagi.';
        }

        $status = (int) $matches[1];
        $detail = preg_match('/ Pesan OpenRouter: (.+)$/u', $exception->getMessage(), $details)
            ? ' Pesan OpenRouter: '.$details[1]
            : '';

        return match (true) {
            $status === 401 => 'OpenRouter tidak mengenali kredensial (HTTP 401). Pastikan OPENROUTER_API_KEY berisi API key aktif dari akun OpenRouter, bukan placeholder; setelah mengubah .env jalankan php artisan config:clear dan mulai ulang server.',
            $status === 403 => 'OpenRouter menolak akses (HTTP 403). Pastikan akun atau model yang dipilih memiliki izin untuk membuat naskah.',
            in_array($status, [402, 429], true) => "Kuota/kredit atau batas permintaan OpenRouter tercapai (HTTP {$status}). Periksa saldo dan batas akun/provider.",
            $status === 400 => 'Permintaan ditolak OpenRouter (HTTP 400). Pastikan OPENROUTER_MODEL sesuai model yang tersedia di akun.',
            $status >= 500 => "OpenRouter sedang bermasalah (HTTP {$status}). Coba beberapa saat lagi.",
            default => "Layanan AI menolak permintaan (HTTP {$status}). Periksa konfigurasi dan coba lagi.",
        }.$detail;
    }

    public function update(Request $request, Event $event, string $tab): RedirectResponse
    {
        Gate::authorize('manage', $event);
        abort_unless(array_key_exists($tab, Script::TYPES), 404);
        $data = $request->validate(['content' => ['required', 'string', 'max:20000']], ['content.required' => 'Naskah tidak boleh kosong.']);
        $data['content'] = Script::cleanDraftContent($data['content']);
        $event->scripts()->where('type', $tab)->firstOrFail()->update(['content' => $data['content'], 'is_ai' => false]);

        return redirect()->route('studio.show', [$event, $tab])->with('status', 'Naskah disimpan.');
    }
}
