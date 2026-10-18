<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Script;
use App\Services\ScriptGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
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
                ->with('error', 'Naskah gagal dibuat. Periksa koneksi atau kunci API, lalu coba lagi.');
        }

        $script = $event->scripts()->where('type', $tab)->first();
        $event->scripts()->updateOrCreate(
            ['type' => $tab],
            ['content' => $text, 'is_ai' => true, 'version' => ($script?->version ?? 0) + 1],
        );

        return redirect()->route('studio.show', [$event, $tab])->with('status', 'Naskah berhasil dibuat.');
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
