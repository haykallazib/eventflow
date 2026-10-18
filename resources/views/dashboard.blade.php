@php
  $nav = 'dashboard';
@endphp
@extends('layouts.app')
@section('title', __('Dasbor'))
@section('heading', __('Dasbor'))
@section('content')
<div class="row sp" style="flex-wrap:wrap;gap:16px">
  <div><h1 class="t24">{{ __('Halo') }}, {{ auth()->user()->displayName() }}</h1><p class="muted">{{ __('Kelola acara, rundown, dan naskah Anda.') }}</p></div>
  <a href="{{ route('events.create') }}" class="btn btn-p btn-block-m"><i data-lucide="plus" class="i"></i> {{ __('Buat Acara') }}</a>
</div>
<div class="g3">
  <div class="card pad row"><span class="chip"><i data-lucide="calendar" class="i"></i></span><div><div class="stat-n">{{ $events->count() }}</div><div class="muted t12">{{ __('Acara') }}</div></div></div>
  <div class="card pad row"><span class="chip ok"><i data-lucide="list-checks" class="i"></i></span><div><div class="stat-n">{{ $totalSessions }}</div><div class="muted t12">{{ __('Baris rundown') }}</div></div></div>
  <div class="card pad row"><span class="chip ai"><i data-lucide="sparkles" class="i"></i></span><div><div class="stat-n">{{ $totalScripts }}</div><div class="muted t12">{{ __('Naskah AI') }}</div></div></div>
</div>
@if($events->isEmpty())
  <div class="card empty">
    <span class="chip"><i data-lucide="calendar-plus" class="i"></i></span>
    <div class="t18">{{ __('Belum ada acara') }}</div>
    <p class="muted">{{ __('Mulai dengan membuat acara pertama Anda.') }}</p>
  </div>
@else
  <div class="g3">
    @foreach($events as $e)
      <div class="card pad col" style="gap:16px">
        <div class="row sp" style="align-items:flex-start">
          <a href="{{ route('rundown.show', $e) }}" class="t18 grow">{{ $e->name }}</a>
        </div>
        <div class="col muted t14" style="gap:4px">
          <span class="row"><i data-lucide="calendar" class="i"></i> {{ $e->event_date->translatedFormat('d M Y') }}</span>
          <span class="row"><i data-lucide="clock" class="i"></i> {{ $e->start_time }} - {{ $e->endTime() }} ({{ $e->durationLabel() }})</span>
          @if($e->location)<span class="row"><i data-lucide="map-pin" class="i"></i> {{ $e->location }}</span>@endif
        </div>
        <div class="row" style="flex-wrap:wrap">
          <span class="badge {{ $e->sessions_count ? 'ok' : '' }}"><span class="dot"></span>{{ $e->sessions_count }} {{ __('baris') }}</span>
          <span class="badge ai"><i data-lucide="sparkles" class="i"></i>{{ $e->scripts_count }}/4 {{ __('naskah') }}</span>
        </div>
        <div class="row" style="margin-top:auto">
          <a class="btn btn-s grow" href="{{ route('rundown.show', $e) }}">{{ __('Buka') }}</a>
          <a class="btn btn-s btn-ic" href="{{ route('events.edit', $e) }}" title="{{ __('Ubah') }}"><i data-lucide="pencil" class="i"></i></a>
          <form method="post" action="{{ route('events.destroy', $e) }}" onsubmit="return confirm('{{ __('Hapus acara ini beserta rundown dan naskahnya?') }}')">@csrf @method('DELETE')
            <button class="btn btn-s btn-ic" title="{{ __('Hapus') }}"><i data-lucide="trash-2" class="i"></i></button></form>
        </div>
      </div>
    @endforeach
  </div>
@endif
@endsection
