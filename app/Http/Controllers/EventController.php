<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\User;
use App\Services\RundownGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class EventController extends Controller
{
    public static function infoRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'theme' => ['nullable', 'string', 'max:200'],
            'location' => ['nullable', 'string', 'max:200'],
            'event_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'duration_hours' => ['required', 'numeric', 'min:0.5', 'max:24'],
            'features' => ['nullable', 'array'],
            'features.*' => ['in:ishoma,sambutan,pidato,mc,doa'],
            'custom_features' => ['nullable', 'array', 'max:10'],
            'custom_features.*' => ['nullable', 'string', 'max:100', 'distinct', 'not_in:ishoma,sambutan,pidato,mc,doa'],
        ];
    }

    public static function infoMessages(): array
    {
        return [
            'name.required' => 'Nama acara wajib diisi.',
            'event_date.required' => 'Tanggal wajib diisi.',
            'start_time.required' => 'Jam mulai wajib diisi.',
            'start_time.date_format' => 'Format jam harus JJ:MM.',
            'duration_hours.required' => 'Durasi wajib diisi.',
            'duration_hours.min' => 'Durasi minimal 0,5 jam.',
        ];
    }

    /** Ubah input formulir menjadi kolom tabel events. */
    public static function toAttributes(array $v): array
    {
        $features = array_values(array_unique(array_merge(
            $v['features'] ?? [],
            array_map(
                fn (string $feature) => 'custom:'.trim($feature),
                array_filter($v['custom_features'] ?? [], fn ($feature) => is_string($feature) && trim($feature) !== ''),
            ),
        )));

        return [
            'name' => $v['name'], 'theme' => $v['theme'] ?? null, 'location' => $v['location'] ?? null,
            'event_date' => $v['event_date'], 'start_time' => $v['start_time'],
            'duration_minutes' => (int) round($v['duration_hours'] * 60),
            'features' => array_values($features),
            'ishoma_enabled' => in_array('ishoma', $features, true),
        ];
    }

    public function index(): View
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        $events = $user->events()->withCount(['sessions', 'scripts'])->orderByDesc('event_date')->get();

        return view('dashboard', [
            'events' => $events,
            'totalSessions' => $events->sum('sessions_count'),
            'totalScripts' => $events->sum('scripts_count'),
        ]);
    }

    public function edit(Event $event): View
    {
        Gate::authorize('manage', $event);

        return view('events.edit', ['event' => $event, 'prayerTimes' => config('eventflow.prayer_times')]);
    }

    public function update(Request $request, Event $event, RundownGenerator $generator): RedirectResponse
    {
        Gate::authorize('manage', $event);
        $data = $request->validate(self::infoRules(), self::infoMessages());
        $event->update(self::toAttributes($data));

        if ($request->boolean('rebuild')) {
            $event->load('speakers')->rebuildRundown($generator);
        }

        return redirect()->route('rundown.show', $event)->with('status', 'Acara diperbarui.');
    }

    public function destroy(Event $event): RedirectResponse
    {
        Gate::authorize('manage', $event);
        $event->delete();

        return redirect()->route('dashboard')->with('status', 'Acara dihapus.');
    }
}
