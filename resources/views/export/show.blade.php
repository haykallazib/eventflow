@php
  $nav = 'export';
@endphp
@extends('layouts.app')
@section('title', __('Ekspor'))
@section('heading', __('Pratinjau Ekspor'))
@section('content')
<div class="row sp" style="flex-wrap:wrap;gap:16px">
  <div><h1 class="t24">{{ __('Pratinjau Ekspor') }}</h1><p class="muted">{{ __('Unduh rundown dan setiap jenis naskah sebagai PDF terpisah, atau unduh semuanya sekaligus.') }}</p></div>
  <a class="btn btn-p" href="{{ route('export.pdf', $event) }}"><i data-lucide="download" class="i"></i> {{ __('Unduh Semua PDF') }}</a>
</div>
<div class="card pad col" style="gap:12px">
  <div class="t18">{{ __('Unduh PDF terpisah') }}</div>
  <div class="row" style="flex-wrap:wrap;gap:8px">
    <a class="btn btn-s" href="{{ route('export.pdf.section', [$event, 'rundown']) }}"><i data-lucide="download" class="i"></i> {{ __('Rundown acara') }}</a>
    @foreach($types as $key => $label)
      @if(isset($scripts[$key]))
        <a class="btn btn-s" href="{{ route('export.pdf.section', [$event, $key]) }}"><i data-lucide="download" class="i"></i> {{ __($label) }}</a>
      @endif
    @endforeach
  </div>
</div>
@include('export._pages')
@endsection
