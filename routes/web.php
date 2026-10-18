<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\EventWizardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\RundownController;
use App\Http\Controllers\ScriptController;
use Illuminate\Support\Facades\Route;

Route::get('/bahasa/{locale}', function (string $locale) {
    abort_unless(in_array($locale, ['id', 'en'], true), 404);
    session(['locale' => $locale]);

    return back();
})->name('lang');

Route::middleware('guest')->group(function () {
    Route::get('/masuk', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/masuk', [AuthController::class, 'login']);
    Route::get('/daftar', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/daftar', [AuthController::class, 'register']);
});

Route::middleware('auth')->group(function () {
    Route::post('/keluar', [AuthController::class, 'logout'])->name('logout');
    Route::get('/', [EventController::class, 'index'])->name('dashboard');

    Route::get('/acara/buat/{step?}', [EventWizardController::class, 'show'])->whereIn('step', [1, 2, 3])->name('events.create');
    Route::post('/acara/buat/{step}', [EventWizardController::class, 'save'])->whereIn('step', [1, 2, 3])->name('events.save');

    Route::get('/acara/{event}/ubah', [EventController::class, 'edit'])->name('events.edit');
    Route::put('/acara/{event}', [EventController::class, 'update'])->name('events.update');
    Route::delete('/acara/{event}', [EventController::class, 'destroy'])->name('events.destroy');

    Route::get('/acara/{event}/rundown', [RundownController::class, 'show'])->name('rundown.show');
    Route::post('/acara/{event}/rundown', [RundownController::class, 'store'])->name('rundown.store');
    Route::post('/acara/{event}/rundown/susun-ulang', [RundownController::class, 'rebuild'])->name('rundown.rebuild');
    Route::put('/acara/{event}/rundown/{session}', [RundownController::class, 'update'])->name('rundown.update');
    Route::delete('/acara/{event}/rundown/{session}', [RundownController::class, 'destroy'])->name('rundown.destroy');
    Route::post('/acara/{event}/rundown/{session}/{dir}', [RundownController::class, 'move'])->whereIn('dir', ['up', 'down'])->name('rundown.move');

    Route::get('/acara/{event}/naskah/{tab?}', [ScriptController::class, 'show'])->name('studio.show');
    Route::post('/acara/{event}/naskah/{tab}/buat', [ScriptController::class, 'generate'])->name('studio.generate');
    Route::put('/acara/{event}/naskah/{tab}', [ScriptController::class, 'update'])->name('studio.update');

    Route::get('/acara/{event}/ekspor', [ExportController::class, 'show'])->name('export.show');
    Route::get('/acara/{event}/ekspor/pdf', [ExportController::class, 'pdf'])->name('export.pdf');
    Route::get('/acara/{event}/ekspor/pdf/{section}', [ExportController::class, 'pdf'])
        ->whereIn('section', ['rundown', ...array_keys(\App\Models\Script::TYPES)])
        ->name('export.pdf.section');
});
