@php
  $nav = 'rundown';
@endphp
@extends('layouts.app')
@section('title', __('Rundown'))
@section('heading', $event->name)
@section('content')
<div class="row sp" style="flex-wrap:wrap;gap:16px">
  <div><h1 class="t24">{{ __('Editor Rundown') }}</h1>
    <p class="muted">{{ $event->event_date->translatedFormat('d F Y') }} · {{ $event->start_time }}–{{ $event->endTime() }} · {{ $event->durationLabel() }}</p></div>
  <div class="row" style="flex-wrap:wrap">
    <form method="post" action="{{ route('rundown.rebuild', $event) }}" onsubmit="return confirm('{{ __('Susun ulang rundown? Penyuntingan manual akan hilang.') }}')">@csrf
      <button class="btn btn-s"><i data-lucide="refresh-cw" class="i"></i> {{ __('Susun ulang') }}</button></form>
    <a class="btn btn-ai" href="{{ route('studio.show', $event) }}"><i data-lucide="sparkles" class="i"></i> {{ __('Buat Naskah dengan AI') }}</a>
  </div>
</div>

<div class="card">
  <div class="rt th"><span></span><span>{{ __('Waktu') }}</span><span></span><span>{{ __('Kegiatan') }}</span><span class="hide-m">{{ __('Durasi') }}</span><span class="hide-m">PIC</span><span class="hide-m"></span></div>
  @forelse($event->sessions as $s)
    @if($editId === $s->id)
      <form method="post" action="{{ route('rundown.update', [$event, $s]) }}" class="rt edit">
        @csrf @method('PUT')
        <span class="grip"><i data-lucide="grip-vertical" class="i"></i></span>
        <span class="tm">{{ $s->start_time }}–{{ $s->endTime() }}</span>
        <span class="rail"><i class="{{ $s->is_ishoma?'w':'' }}"></i></span>
        <input class="inp sm" name="title" value="{{ old('title', $s->title) }}">
        <input class="inp sm" type="number" min="1" name="duration_minutes" value="{{ old('duration_minutes', $s->duration_minutes) }}">
        <input class="inp sm" name="pic" value="{{ old('pic', $s->pic) }}" placeholder="PIC">
        <span class="row"><button class="btn btn-p">{{ __('Simpan') }}</button><a class="btn btn-g" href="{{ route('rundown.show', $event) }}">{{ __('Batal') }}</a></span>
      </form>
    @else
      <div class="rt {{ $s->is_ishoma?'ish':'' }}">
        <span class="grip hide-m"><i data-lucide="grip-vertical" class="i"></i></span>
        <span class="tm">{{ $s->start_time }}–{{ $s->endTime() }}</span>
        <span class="rail hide-m"><i class="{{ $s->is_ishoma?'w':'' }}"></i></span>
        <span class="grow"><span class="b">{{ $s->title }}</span>@if($s->note)<br><span class="t12 muted row" style="gap:4px"><i data-lucide="moon" class="i" style="width:14px;height:14px"></i>{{ __($s->note) }}</span>@endif</span>
        <span class="muted">{{ $s->duration_minutes }} {{ __('mnt') }}</span>
        <span class="muted hide-m">{{ $s->pic }}</span>
        <span class="row" style="gap:0;justify-content:flex-end">
          @foreach(['up'=>'chevron-up','down'=>'chevron-down'] as $d=>$ic)
            <form class="inl" method="post" action="{{ route('rundown.move', [$event, $s, $d]) }}">@csrf<button title="{{ $d }}"><i data-lucide="{{ $ic }}" class="i"></i></button></form>
          @endforeach
          <a class="inl" href="{{ route('rundown.show', [$event, 'edit' => $s->id]) }}"><button type="button" title="{{ __('Ubah') }}"><i data-lucide="pencil" class="i"></i></button></a>
          <form class="inl" method="post" action="{{ route('rundown.destroy', [$event, $s]) }}" onsubmit="return confirm('{{ __('Hapus baris ini?') }}')">@csrf @method('DELETE')<button title="{{ __('Hapus') }}"><i data-lucide="trash-2" class="i"></i></button></form>
        </span>
      </div>
    @endif
  @empty
    <div class="empty"><div class="t18">{{ __('Rundown masih kosong') }}</div></div>
  @endforelse
</div>

<form method="post" action="{{ route('rundown.store', $event) }}" class="card pad col">
  @csrf
  <div class="t16 b">{{ __('Tambah baris') }}</div>
  <div class="g3">
    <div><label class="lbl">{{ __('Kegiatan') }}</label><input class="inp sm" name="title" required></div>
    <div><label class="lbl">{{ __('Durasi (menit)') }}</label><input class="inp sm" type="number" min="1" name="duration_minutes" value="15" required></div>
    <div><label class="lbl">PIC</label><input class="inp sm" name="pic"></div>
  </div>
  @if($errors->any())<div class="errtxt">{{ __($errors->first()) }}</div>@endif
  <div><button class="btn btn-s"><i data-lucide="plus" class="i"></i> {{ __('Tambah') }}</button></div>
</form>
@endsection
