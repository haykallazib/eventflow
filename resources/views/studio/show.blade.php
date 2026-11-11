@php
  $nav = 'studio';
@endphp
@extends('layouts.app')
@section('title', __('Naskah AI'))
@section('heading', $event->name)
@section('content')
@php $types = \App\Models\Script::TYPES; @endphp
<div class="row sp" style="flex-wrap:wrap;gap:16px">
  <div><h1 class="t24">{{ __('Studio Naskah AI') }}</h1>
    <p class="muted">{{ $event->name }}</p></div>
  <a class="btn btn-s" href="{{ route('export.show', $event) }}"><i data-lucide="file-down" class="i"></i> {{ __('Pratinjau & Unduh PDF') }}</a>
</div>
@unless($usesAi)
  <div class="card pad row" style="gap:12px;background:var(--ai-soft);box-shadow:none"><i data-lucide="info" class="i aic"></i><span class="t14">{{ __('Mode contoh: kunci API AI belum diisi, naskah dibuat dari templat bawaan.') }}</span></div>
@endunless
<div class="tabs">
  @foreach($types as $k => $label)
    <a class="tab {{ $tab===$k?'on':'' }}" href="{{ route('studio.show', [$event, $k]) }}">{{ __($label) }}</a>
  @endforeach
</div>
@if($tab === 'sambutan')
  <div class="card pad t14" style="background:var(--ai-soft);box-shadow:none">
    {{ __('Draf dibuat untuk setiap sesi Sambutan di rundown. Setiap sesi memiliki versi formal dan versi nonformal yang lebih hangat dan santai, tetapi tetap sopan.') }}
  </div>
@elseif($tab === 'pidato')
  <div class="card pad t14" style="background:var(--ai-soft);box-shadow:none">
    {{ __('Draf dibuat untuk setiap sesi pemateri di rundown. Setiap sesi memiliki versi formal dan versi nonformal yang lebih hangat dan santai, tetapi tetap informatif.') }}
  </div>
@elseif($tab === 'doa')
  <div class="card pad t14" style="background:var(--ai-soft);box-shadow:none">
    {{ __('Pilih agama sebelum membuat draf. Naskah doa akan dibuat lebih panjang dan disesuaikan dengan agama yang dipilih.') }}
  </div>
@endif

<div id="studio">
  <div class="skel col card pad" style="gap:16px">
    <div class="row b aic"><i data-lucide="sparkles" class="i"></i> {{ __('AI sedang menulis naskah...') }}</div>
    <div class="pbar"><i style="width:60%"></i></div>
    <div class="sk ai" style="width:92%"></div><div class="sk ai" style="width:80%"></div><div class="sk ai" style="width:88%"></div><div class="sk ai" style="width:60%"></div>
  </div>

  <div class="draft-wrap">
  @if($script)
    <form method="post" action="{{ route('studio.update', [$event, $tab]) }}" class="card pad col" style="gap:16px">
      @csrf @method('PUT')
      <div class="row sp" style="flex-wrap:wrap">
        <span class="t18">{{ __($types[$tab]) }}</span>
        <span class="row">@if($script->is_ai)<span class="badge ai"><i data-lucide="sparkles" class="i"></i>{{ __('Draf AI') }}</span>@else<span class="badge p">{{ __('Disunting') }}</span>@endif<span class="badge">v{{ $script->version }}</span></span>
      </div>
      <textarea class="inp" name="content" rows="20" style="text-align:justify;line-height:1.8;font-size:15px;padding:18px;white-space:pre-wrap">{{ old('content', $scriptContent) }}</textarea>
      @error('content')<div class="errtxt">{{ __($message) }}</div>@enderror
      <div class="row" style="justify-content:flex-end;flex-wrap:wrap">
        <button type="button" class="btn btn-s" onclick="navigator.clipboard.writeText(this.form.content.value)"><i data-lucide="copy" class="i"></i> {{ __('Salin') }}</button>
        <button class="btn btn-p">{{ __('Simpan') }}</button>
      </div>
    </form>
    <form method="post" action="{{ route('studio.generate', [$event, $tab]) }}" class="gen" style="margin-top:16px">@csrf
      @if($tab === 'doa') @include('studio._religion') @endif
      <button class="btn btn-aio"><i data-lucide="sparkles" class="i"></i> {{ __('Buat Ulang dengan AI') }}</button></form>
  @else
    <div class="card empty">
      <span class="chip ai"><i data-lucide="sparkles" class="i"></i></span>
      <div class="t18">{{ __('Belum ada naskah') }} {{ __($types[$tab]) }}</div>
      <p class="muted">{{ __('AI akan memakai data acara dan rundown Anda.') }}</p>
      <form method="post" action="{{ route('studio.generate', [$event, $tab]) }}" class="gen">@csrf
        @if($tab === 'doa') @include('studio._religion') @endif
        <button class="btn btn-ai btn-lg"><i data-lucide="sparkles" class="i"></i> {{ __('Buat Naskah dengan AI') }}</button></form>
    </div>
  @endif
  </div>
</div>
@push('scripts')
<script>document.querySelectorAll('form.gen').forEach(function(f){f.addEventListener('submit',function(){document.getElementById('studio').classList.add('busy');f.querySelector('button').disabled=true;})});</script>
@endpush
@endsection
