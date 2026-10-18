<?php

/*
| Konfigurasi domain EventFlow.
| Jadwal salat di bawah adalah data contoh statis (sesuai prototipe).
| Ganti nilainya sesuai kota acara, atau hubungkan ke layanan jadwal salat.
*/
return [
    // Ishoma disisipkan otomatis bila durasi acara melebihi batas ini (menit).
    'ishoma_threshold_minutes' => 240,
    'ishoma_default_minutes' => 60,

    'prayer_times' => [
        'Dzuhur' => '11:45',
        'Ashar' => '15:00',
        'Maghrib' => '17:45',
        'Isya' => '19:00',
    ],

    'features' => [
        'ishoma' => 'Ishoma otomatis',
        'sambutan' => 'Sambutan',
        'pidato' => 'Pidato utama',
        'mc' => 'Naskah MC',
        'doa' => 'Doa',
    ],

    'prayer_religions' => [
        'islam' => 'Islam',
        'kristen' => 'Kristen (Protestan)',
        'katolik' => 'Katolik',
        'hindu' => 'Hindu',
        'buddha' => 'Buddha',
        'konghucu' => 'Konghucu',
    ],
];
