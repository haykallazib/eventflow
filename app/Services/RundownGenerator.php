<?php

namespace App\Services;

/**
 * Menyusun rundown otomatis dari data acara. Kelas ini murni PHP (tanpa
 * database) supaya mudah diuji dan dipakai untuk pratinjau sebelum disimpan.
 *
 * Aturan Ishoma: jika durasi > ambang (default 240 menit) dan fitur Ishoma
 * aktif, satu baris Ishoma disisipkan pada batas segmen yang paling dekat
 * dengan waktu salat di dalam rentang acara.
 */
class RundownGenerator
{
    public function __construct(
        private array $prayerTimes = [],
        private int $threshold = 240,
    ) {
        if (! $this->prayerTimes && function_exists('config')) {
            $this->prayerTimes = config('eventflow.prayer_times', []);
            $this->threshold = (int) config('eventflow.ishoma_threshold_minutes', 240);
        }
    }

    /**
     * @param array{start_time:string,duration_minutes:int,ishoma_enabled?:bool,ishoma_minutes?:int,features?:array} $event
     * @param array<int,array{kind:string,name:string,role?:?string,topic?:?string}> $speakers
     * @return array<int,array<string,mixed>>
     */
    public function generate(array $event, array $speakers = []): array
    {
        $features = $event['features'] ?? [];
        $has = fn (string $f) => in_array($f, $features, true);
        $start = $this->toMinutes($event['start_time']);
        $total = (int) $event['duration_minutes'];
        $end = $start + $total;

        $useIshoma = ($event['ishoma_enabled'] ?? $has('ishoma')) && $total > $this->threshold;
        $ishomaMin = $useIshoma ? (int) ($event['ishoma_minutes'] ?? 60) : 0;

        $welcome = array_values(array_filter($speakers, fn ($s) => $s['kind'] === 'sambutan'));
        $guests = array_values(array_filter($speakers, fn ($s) => $s['kind'] === 'pemateri'));
        $customFeatures = array_values(array_filter(
            $features,
            fn ($feature) => is_string($feature) && str_starts_with($feature, 'custom:'),
        ));

        // Segmen pembuka (sebelum sesi utama) dan penutup.
        $opening = [['Pembukaan oleh MC', 'pembukaan', 10, 'MC']];
        if ($has('doa')) {
            $opening[] = ['Doa Pembuka', 'doa', 5, 'Pembaca doa'];
        }
        if ($has('sambutan')) {
            if (! $welcome) {
                $welcome = [['kind' => 'sambutan', 'name' => 'Ketua Panitia', 'role' => null]];
            }
            foreach ($welcome as $w) {
                $opening[] = ['Sambutan: '.$w['name'], 'sambutan', 10, $w['name']];
            }
        }
        if ($has('pidato')) {
            $opening[] = ['Pidato Utama', 'pidato', 20, 'Pembicara utama'];
        }
        $closing = [['Penutup oleh MC', 'penutup', 10, 'MC']];
        if ($has('doa')) {
            $closing[] = ['Doa Penutup', 'doa', 5, 'Pembaca doa'];
        }

        $fixed = array_sum(array_column($opening, 2)) + array_sum(array_column($closing, 2));
        $remaining = max(0, $total - $fixed - $ishomaMin);

        // Divide the available time evenly across speaker and custom activities.
        $activities = array_map(
            fn ($guest) => [$guest['topic'] ?: 'Sesi: '.$guest['name'], 'sesi', 0, $guest['name']],
            $guests,
        );
        foreach ($customFeatures as $feature) {
            $activities[] = [trim(substr($feature, strlen('custom:'))), 'fitur', 0, 'Panitia'];
        }
        if (! $activities) {
            $activities[] = ['Sesi Utama', 'sesi', 0, 'Narasumber'];
        }
        if ($remaining > 0) {
            $baseDuration = intdiv($remaining, count($activities));
            $extraMinutes = $remaining % count($activities);
            foreach ($activities as $i => &$activity) {
                $activity[2] = $baseDuration + ($i < $extraMinutes ? 1 : 0);
            }
            unset($activity);
        } else {
            $activities = [];
        }

        $segments = array_merge($opening, $activities, $closing);

        $rows = [];
        $cursor = $start;
        foreach ($segments as $seg) {
            $rows[] = [
                'title' => $seg[0], 'type' => $seg[1], 'duration_minutes' => $seg[2],
                'pic' => $seg[3], 'note' => null, 'is_ishoma' => false, 'start' => $cursor,
            ];
            $cursor += $seg[2];
        }

        if ($useIshoma && count($rows) > 1) {
            [$prayer, $target] = $this->pickPrayer($start, $end, $ishomaMin);
            // Bila batas segmen terlalu jauh dari waktu salat, pecah sesi utama yang melintasinya.
            $nearest = min(array_map(fn ($r) => abs($r['start'] - $target), array_slice($rows, 1)));
            if ($nearest > 15) {
                foreach ($rows as $i => $r) {
                    $cut = (int) (round(($target - $r['start']) / 5) * 5);
                    if ($r['type'] === 'sesi' && $cut >= 15 && $r['duration_minutes'] - $cut >= 15) {
                        $second = $r;
                        $second['title'] = $r['title'].' (Bagian 2)';
                        $second['duration_minutes'] = $r['duration_minutes'] - $cut;
                        $second['start'] = $r['start'] + $cut;
                        $rows[$i]['title'] = $r['title'].' (Bagian 1)';
                        $rows[$i]['duration_minutes'] = $cut;
                        array_splice($rows, $i + 1, 0, [$second]);
                        break;
                    }
                }
            }
            // Batas segmen terdekat dengan waktu target (tidak di posisi 0 atau setelah penutup).
            $bestIdx = 1;
            $bestGap = PHP_INT_MAX;
            for ($i = 1; $i < count($rows); $i++) {
                $gap = abs($rows[$i]['start'] - $target);
                if ($gap < $bestGap) {
                    $bestGap = $gap;
                    $bestIdx = $i;
                }
            }
            array_splice($rows, $bestIdx, 0, [[
                'title' => 'Ishoma (Istirahat, Salat, Makan)', 'type' => 'ishoma',
                'duration_minutes' => $ishomaMin, 'pic' => 'Konsumsi',
                'note' => $prayer ? 'Selaras dengan waktu salat '.$prayer : 'Selaras dengan waktu salat',
                'is_ishoma' => true, 'start' => 0,
            ]]);
        }

        // Hitung ulang jam setiap baris berurutan dari jam mulai.
        $cursor = $start;
        foreach ($rows as $i => &$r) {
            $r['position'] = $i + 1;
            $r['start'] = $cursor;
            $r['start_time'] = $this->fmt($cursor);
            $r['end_time'] = $this->fmt($cursor + $r['duration_minutes']);
            $cursor += $r['duration_minutes'];
            unset($r['start']);
        }

        return $rows;
    }

    /** Pilih waktu salat dalam rentang acara yang paling dekat dengan titik tengah acara. */
    private function pickPrayer(int $start, int $end, int $ishomaMin): array
    {
        $mid = intdiv($start + $end, 2);
        $best = null;
        foreach ($this->prayerTimes as $name => $time) {
            $t = $this->toMinutes($time);
            if ($t < $start || $t + $ishomaMin > $end) {
                continue;
            }
            if ($best === null || abs($t - $mid) < abs($best[1] - $mid)) {
                $best = [$name, $t];
            }
        }

        return $best ?? [null, $mid];
    }

    public function toMinutes(string $hhmm): int
    {
        [$h, $m] = array_map('intval', explode(':', $hhmm));

        return $h * 60 + $m;
    }

    public function fmt(int $min): string
    {
        $min %= 1440;

        return sprintf('%02d:%02d', intdiv($min, 60), $min % 60);
    }
}
