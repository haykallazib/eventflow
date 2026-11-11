<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'EventFlow') - EventFlow</title>
@include('partials.theme-init')
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/eventflow.css') }}">
<script src="https://unpkg.com/lucide@0.469.0/dist/umd/lucide.min.js" defer></script>
@stack('head')
</head>
<body>
@php
  $ev = $event ?? null;
  $active = $nav ?? '';
  $tabs = [['dashboard', route('dashboard'), 'layout-dashboard', 'Dasbor']];
  $tabs[] = ['create', route('events.create'), 'plus-circle', 'Buat Acara'];
  if ($ev) { $tabs[] = ['rundown', route('rundown.show', $ev), 'list-checks', 'Rundown']; $tabs[] = ['studio', route('studio.show', $ev), 'sparkles', 'Naskah AI']; }
@endphp
<div class="app">
  <aside class="side">
    <div class="brand"><span class="logo"><i data-lucide="calendar-check" class="i"></i></span> EventFlow</div>
    <a class="nav {{ $active==='dashboard'?'on':'' }}" href="{{ route('dashboard') }}"><i data-lucide="layout-dashboard" class="i"></i> {{ __('Dasbor') }}</a>
    <a class="nav {{ $active==='create'?'on':'' }}" href="{{ route('events.create') }}"><i data-lucide="plus-circle" class="i"></i> {{ __('Buat Acara') }}</a>
    @if($ev)
      <div class="navh">{{ \Illuminate\Support\Str::limit($ev->name, 26) }}</div>
      <a class="nav {{ $active==='rundown'?'on':'' }}" href="{{ route('rundown.show', $ev) }}"><i data-lucide="list-checks" class="i"></i> {{ __('Rundown') }}</a>
      <a class="nav {{ $active==='studio'?'on':'' }}" href="{{ route('studio.show', $ev) }}"><i data-lucide="sparkles" class="i"></i> {{ __('Naskah AI') }}</a>
      <a class="nav {{ $active==='export'?'on':'' }}" href="{{ route('export.show', $ev) }}"><i data-lucide="file-down" class="i"></i> {{ __('Ekspor') }}</a>
    @endif
    <div class="me">
      <span class="av">{{ auth()->user()->initials() }}</span>
      <div class="grow"><div class="b t14">{{ auth()->user()->displayName() }}</div><div class="t12 muted">{{ '@'.auth()->user()->username }}</div></div>
      <form method="post" action="{{ route('logout') }}">@csrf<button class="btn btn-g btn-ic" title="{{ __('Keluar') }}"><i data-lucide="log-out" class="i"></i></button></form>
    </div>
  </aside>
  <div class="main">
    <header class="top">
      <div class="t18 grow">@yield('heading')</div>
      @include('partials.theme-toggle')
    </header>
    <header class="mtop">
      <span class="logo"><i data-lucide="calendar-check" class="i"></i></span>
      <div class="t16 b grow">@yield('heading')</div>
      @include('partials.theme-toggle')
      <form method="post" action="{{ route('logout') }}">@csrf<button class="btn btn-g btn-ic" title="{{ __('Keluar') }}"><i data-lucide="log-out" class="i"></i></button></form>
    </header>
    <main class="page">
      @if(session('status'))
        <div class="toast alert-ok noprint"><span class="ti ok"><i data-lucide="check" class="i"></i></span><div class="grow"><div class="b">{{ __(session('status')) }}</div></div></div>
      @endif
      @if(session('error'))
        <div class="toast noprint"><span class="ti err"><i data-lucide="alert-triangle" class="i"></i></span><div class="grow"><div class="b">{{ __('Terjadi kesalahan') }}</div><div class="t12 muted">{{ __(session('error')) }}</div></div></div>
      @endif
      @yield('content')
    </main>
    <nav class="mtabs" style="grid-template-columns:repeat({{ count($tabs) }},minmax(0,1fr))">
      @foreach($tabs as [$k,$url,$ic,$lb])
        <a class="mt {{ $active===$k?'on':'' }}" href="{{ $url }}"><i data-lucide="{{ $ic }}" class="i"></i>{{ __($lb) }}</a>
      @endforeach
    </nav>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded',function(){ if(window.lucide) lucide.createIcons(); });
</script>
@include('partials.theme-script')
@stack('scripts')
</body>
</html>
