<?php

namespace Tests\Feature;

use App\Services\RundownGenerator;
use PHPUnit\Framework\TestCase;

class RundownGeneratorTest extends TestCase
{
    private function gen(): RundownGenerator
    {
        return new RundownGenerator(['Dzuhur' => '11:45', 'Ashar' => '15:00'], 240);
    }

    private function event(int $minutes): array
    {
        return ['start_time' => '08:00', 'duration_minutes' => $minutes, 'ishoma_enabled' => true, 'features' => ['sambutan', 'pidato', 'doa']];
    }

    public function test_six_hour_event_gets_ishoma_at_dzuhur(): void
    {
        $rows = $this->gen()->generate($this->event(360));
        $ish = array_values(array_filter($rows, fn ($r) => $r['is_ishoma']));
        $this->assertCount(1, $ish);
        $this->assertSame('11:45', $ish[0]['start_time']);
        $this->assertStringContainsString('waktu salat', $ish[0]['note']);
        $this->assertSame('14:00', end($rows)['end_time']);
    }

    public function test_short_event_has_no_ishoma(): void
    {
        $rows = $this->gen()->generate($this->event(180));
        $this->assertEmpty(array_filter($rows, fn ($r) => $r['is_ishoma']));
        $this->assertSame(180, array_sum(array_column($rows, 'duration_minutes')));
    }

    public function test_custom_features_are_rundown_activities_and_share_remaining_time(): void
    {
        $event = $this->event(180);
        $event['features'][] = 'custom:Sesi Tanya Jawab';
        $speakers = [['kind' => 'pemateri', 'name' => 'Narasumber', 'topic' => 'Materi']];

        $rows = $this->gen()->generate($event, $speakers);
        $custom = array_values(array_filter($rows, fn ($row) => $row['type'] === 'fitur'));

        $this->assertCount(1, $custom);
        $this->assertSame('Sesi Tanya Jawab', $custom[0]['title']);
        $this->assertSame(60, $custom[0]['duration_minutes']);
        $this->assertSame(180, array_sum(array_column($rows, 'duration_minutes')));
    }
}
