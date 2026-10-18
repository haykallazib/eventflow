<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\RundownGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/** Pembuatan acara 3 langkah. Data antar langkah disimpan sementara di session. */
class EventWizardController extends Controller
{
    public function show(Request $request, RundownGenerator $generator, int $step = 1): View|RedirectResponse
    {
        $wizard = $request->session()->get('wizard', []);
        if ($step > 1 && empty($wizard['info'])) {
            return redirect()->route('events.create', 1);
        }

        $data = ['step' => $step, 'wizard' => $wizard, 'prayerTimes' => config('eventflow.prayer_times')];

        if ($step === 3) {
            $info = EventController::toAttributes($wizard['info']);
            $data['info'] = $info;
            $data['speakers'] = $wizard['speakers'] ?? [];
            $data['preview'] = $generator->generate($info, $data['speakers']);
        }

        return view('events.wizard', $data);
    }

    public function save(Request $request, RundownGenerator $generator, int $step): RedirectResponse
    {
        if ($step === 1) {
            $data = $request->validate(EventController::infoRules(), EventController::infoMessages());
            $data['features'] = $data['features'] ?? [];
            $request->session()->put('wizard.info', $data);

            return redirect()->route('events.create', 2);
        }

        if ($step === 2) {
            $rows = collect($request->input('speakers', []))
                ->filter(fn ($r) => filled($r['name'] ?? null))
                ->map(fn ($r) => [
                    'kind' => ($r['kind'] ?? '') === 'sambutan' ? 'sambutan' : 'pemateri',
                    'name' => mb_substr(trim($r['name']), 0, 150),
                    'role' => filled($r['role'] ?? null) ? mb_substr(trim($r['role']), 0, 150) : null,
                    'topic' => filled($r['topic'] ?? null) ? mb_substr(trim($r['topic']), 0, 200) : null,
                ])->values()->all();
            $request->session()->put('wizard.speakers', $rows);

            return redirect()->route('events.create', 3);
        }

        // Langkah 3: simpan acara + rundown.
        $wizard = $request->session()->get('wizard');
        abort_if(empty($wizard['info']), 404);

        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        $event = $user->events()->create(EventController::toAttributes($wizard['info']));
        foreach ($wizard['speakers'] ?? [] as $s) {
            $event->speakers()->create($s);
        }
        $event->load('speakers')->rebuildRundown($generator);
        $request->session()->forget('wizard');

        return redirect()->route('rundown.show', $event)->with('status', 'Rundown berhasil disusun.');
    }
}
