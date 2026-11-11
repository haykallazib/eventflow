<!doctype html>
<html lang="{{ app()->getLocale() }}"><head><meta charset="utf-8">
<style>
  @page{size:A4;margin:30mm 30mm 30mm 40mm}
  body{font-family:'DejaVu Sans',sans-serif;font-size:11pt;color:#0F172A;line-height:1.7}
  h1{font-size:18pt;margin:0 0 6px} h2{font-size:15pt;margin:0 0 14px;color:#4F46E5}
  .muted{color:#64748B;font-size:9pt}
  table{width:100%;border-collapse:collapse;margin-top:12px}
  th{background:#EEF2FF;color:#4F46E5;text-align:left;font-size:9pt;padding:6px;border-bottom:1px solid #E2E8F0}
  td{padding:6px;border-bottom:1px solid #E2E8F0;vertical-align:top}
  tr.ish td{background:#FEF3C7}
  .pb{page-break-before:always}
  .script{margin:0 0 12px;text-align:justify;line-height:1.8;overflow-wrap:break-word}
  .event-meta{margin:0 0 20px}
  .rundown-title{font-size:15pt;color:#4F46E5;margin:0 0 14px}
  .script-title{font-size:15pt;color:#4F46E5;margin:0 0 18px}
</style></head><body>
@if($section !== 'all' && $section !== 'rundown')
  <h1 class="script-title">{{ __($types[$section]) }}</h1>
@endif
@if($section === 'all' || $section === 'rundown')
<div class="muted">{{ __('RUNDOWN ACARA') }}</div>
<h1>{{ $event->name }}</h1>
<div class="muted event-meta">{{ $event->event_date->translatedFormat('l, d F Y') }} · {{ $event->start_time }}–{{ $event->endTime() }} WIB @if($event->location)· {{ $event->location }}@endif</div>
<table>
  <tr><th style="width:90px">{{ __('Waktu') }}</th><th>{{ __('Kegiatan') }}</th><th style="width:55px">{{ __('Durasi') }}</th><th style="width:110px">PIC</th></tr>
  @foreach($event->sessions as $s)
  <tr class="{{ $s->is_ishoma?'ish':'' }}"><td>{{ $s->start_time }}–{{ $s->endTime() }}</td>
    <td><b>{{ __($s->title) }}</b>@if($s->note)<br><span class="muted">{{ __($s->note) }}</span>@endif</td>
    <td>{{ $s->duration_minutes }} {{ __('mnt') }}</td><td>{{ $s->pic }}</td></tr>
  @endforeach
</table>
@endif
@if($section === 'all' && $scripts->isNotEmpty())
<div class="pb"></div>
@php $availableTypes = collect($types)->filter(fn ($label, $key) => isset($scripts[$key])); @endphp
@foreach($availableTypes as $k => $label)
  <h2>{{ __($label) }}</h2>
  @php $paragraphs = preg_split('/\R{2,}/u', trim($scripts[$k]->content)) ?: []; @endphp
  @foreach($paragraphs as $paragraph)
    <p class="script">{{ $paragraph }}</p>
  @endforeach
  @if(!$loop->last)<div class="pb"></div>@endif
@endforeach
@elseif($section !== 'all' && $section !== 'rundown' && $script)
@php $paragraphs = preg_split('/\R{2,}/u', trim($script->content)) ?: []; @endphp
@foreach($paragraphs as $paragraph)
  <p class="script">{{ $paragraph }}</p>
@endforeach
@endif
</body></html>
