<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventSession;
use App\Services\RundownGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class RundownController extends Controller
{
    public function show(Request $request, Event $event): View
    {
        Gate::authorize('manage', $event);

        return view('rundown.show', [
            'event' => $event->load('sessions'),
            'editId' => (int) $request->query('edit'),
        ]);
    }

    public function update(Request $request, Event $event, EventSession $session): RedirectResponse
    {
        $this->own($event, $session);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:600'],
            'pic' => ['nullable', 'string', 'max:150'],
        ], ['title.required' => 'Judul wajib diisi.', 'duration_minutes.required' => 'Durasi wajib diisi.']);
        $session->update($data);
        $event->recalculateTimes();

        return redirect()->route('rundown.show', $event)->with('status', 'Baris rundown diperbarui.');
    }

    public function store(Request $request, Event $event): RedirectResponse
    {
        Gate::authorize('manage', $event);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:600'],
            'pic' => ['nullable', 'string', 'max:150'],
        ], ['title.required' => 'Judul wajib diisi.', 'duration_minutes.required' => 'Durasi wajib diisi.']);

        // Sisipkan sebelum baris penutup terakhir agar urutan tetap masuk akal.
        $count = $event->sessions()->count();
        $position = max(1, $count);
        $event->sessions()->where('position', '>=', $position)->increment('position');
        $event->sessions()->create($data + ['type' => 'sesi', 'start_time' => $event->start_time, 'position' => $position]);
        $event->recalculateTimes();

        return redirect()->route('rundown.show', $event)->with('status', 'Baris ditambahkan.');
    }

    public function move(Event $event, EventSession $session, string $dir): RedirectResponse
    {
        $this->own($event, $session);
        $rows = $event->sessions()->get()->values();
        $i = $rows->search(fn ($r) => $r->id === $session->id);
        $j = $dir === 'up' ? $i - 1 : $i + 1;
        if ($j >= 0 && $j < $rows->count()) {
            $a = $rows[$i]->position;
            $rows[$i]->update(['position' => $rows[$j]->position]);
            $rows[$j]->update(['position' => $a]);
            $event->recalculateTimes();
        }

        return redirect()->route('rundown.show', $event);
    }

    public function destroy(Event $event, EventSession $session): RedirectResponse
    {
        $this->own($event, $session);
        $session->delete();
        $event->recalculateTimes();

        return redirect()->route('rundown.show', $event)->with('status', 'Baris dihapus.');
    }

    public function rebuild(Event $event, RundownGenerator $generator): RedirectResponse
    {
        Gate::authorize('manage', $event);
        $event->load('speakers')->rebuildRundown($generator);

        return redirect()->route('rundown.show', $event)->with('status', 'Rundown disusun ulang.');
    }

    private function own(Event $event, EventSession $session): void
    {
        Gate::authorize('manage', $event);
        abort_unless($session->event_id === $event->id, 404);
    }
}
