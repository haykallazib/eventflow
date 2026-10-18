<div class="card pad col" style="gap:16px;max-width:794px;width:100%;background:var(--surface)">
  <div class="t12 muted b">{{ __('RUNDOWN ACARA') }}</div>
  <h2 class="t24">{{ $event->name }}</h2>
  <p class="muted">{{ $event->event_date->translatedFormat('l, d F Y') }} · {{ $event->start_time }}–{{ $event->endTime() }} WIB @if($event->location)· {{ $event->location }}@endif</p>
  @foreach($event->sessions as $s)
    <div class="rv {{ $s->is_ishoma?'ish':'' }}" style="padding:0 8px">
      <span class="tm">{{ $s->start_time }}–{{ $s->endTime() }}</span><span class="rail"><i class="{{ $s->is_ishoma?'w':'' }}"></i></span>
      <span class="b">{{ $s->title }}@if($s->note)<br><span class="t12 muted">{{ __($s->note) }}</span>@endif</span>
      <span class="hide-m muted">{{ $s->duration_minutes }} {{ __('mnt') }}</span><span class="hide-m muted">{{ $s->pic }}</span>
    </div>
  @endforeach
</div>
<div class="col" style="gap:16px;max-width:794px;width:100%">
  <div class="t12 muted b">{{ __('NASKAH') }}</div>
  @forelse($types as $k => $label)
    @if(isset($scripts[$k]))
      <article class="card pad col" style="gap:14px;background:var(--surface)">
        <div class="row sp"><div class="t18">{{ __($label) }}</div><a class="btn btn-s" href="{{ route('export.pdf.section', [$event, $k]) }}"><i data-lucide="download" class="i"></i> {{ __('Unduh PDF') }}</a></div>
        <div class="pre" style="font-size:15px;line-height:1.8;text-align:justify;white-space:pre-wrap">{{ $scripts[$k]->content }}</div>
      </article>
    @endif
  @empty @endforelse
  @if($scripts->isEmpty())<div class="card pad" style="background:var(--surface)"><p class="muted">{{ __('Belum ada naskah. Buat naskah di Studio Naskah AI.') }}</p></div>@endif
</div>
