<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Script;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ExportController extends Controller
{
    public function show(Event $event): View
    {
        return view('export.show', $this->data($event));
    }

    public function pdf(Event $event, ?string $section = null)
    {
        abort_if($section !== null && $section !== 'rundown' && ! array_key_exists($section, Script::TYPES), 404);
        $data = $this->data($event);
        $data['section'] = $section ?? 'all';
        if ($section !== null && $section !== 'rundown') {
            $data['script'] = $data['scripts']->get($section);
            abort_unless($data['script'] instanceof Script && filled($data['script']->content), 404, 'Naskah tidak tersedia atau kosong.');
        } elseif ($section === 'rundown') {
            $data['scripts'] = collect();
            $data['script'] = null;
        } else {
            $data['script'] = null;
        }

        $filename = str($event->name)->slug();
        if ($section !== null) {
            $filename = $filename->append('-'.$section);
        }

        return Pdf::loadView('export.pdf', $data)->setPaper('a4')
            ->download($filename->append('.pdf')->toString());
    }

    private function data(Event $event): array
    {
        Gate::authorize('manage', $event);
        $event->load('sessions', 'speakers');
        $scripts = $event->scripts->keyBy('type');
        $scripts->each(function (Script $script): void {
            $script->content = Script::cleanDraftContent($script->content);
        });

        return ['event' => $event, 'scripts' => $scripts, 'types' => Script::TYPES];
    }
}
