<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\RundownGenerator;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /** Akun demo: username "demo", kata sandi "password123". */
    public function run(): void
    {
        $user = User::firstOrCreate(['username' => 'demo'], ['name' => 'Panitia Demo', 'password' => 'password123']);

        $event = $user->events()->create([
            'name' => 'Seminar Nasional Teknologi 2026',
            'theme' => 'Inovasi AI untuk Pendidikan',
            'location' => 'Aula Utama Kampus',
            'event_date' => now()->addMonth()->toDateString(),
            'start_time' => '08:00',
            'duration_minutes' => 360,
            'ishoma_enabled' => true,
            'features' => ['ishoma', 'sambutan', 'pidato', 'mc', 'doa'],
        ]);
        foreach ([
            ['sambutan', 'Dr. Rina Wulandari', 'Rektor', null],
            ['pemateri', 'Prof. Budi Santoso', 'Guru Besar Informatika', 'Masa Depan AI di Kampus'],
            ['pemateri', 'Sari Anggraini, M.Kom.', 'Praktisi Data', 'Etika dan Keamanan Data'],
        ] as [$kind, $name, $role, $topic]) {
            $event->speakers()->create(compact('kind', 'name', 'role', 'topic'));
        }
        $event->load('speakers')->rebuildRundown(app(RundownGenerator::class));
    }
}
