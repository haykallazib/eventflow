<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title') - EventFlow</title>
@include('partials.theme-init')
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/eventflow.css') }}">
<style>
.gsplit{display:flex;min-height:100vh}
.gform{flex:1 1 480px;display:flex;flex-direction:column;padding:32px 48px;background:var(--surface)}
.gform .wrap{width:100%;max-width:400px;margin:auto;display:flex;flex-direction:column;gap:24px}
.gside{flex:1 1 560px;background:var(--primary-soft);display:grid;place-items:center;padding:48px}
.gsplit.login-page .gside{
  --login-image-tint:rgba(37,43,77,.22);
  background:
    linear-gradient(var(--login-image-tint), var(--login-image-tint)),
    url('{{ asset('images/login-background.jpg') }}') center / cover no-repeat;
  color:#fff;
}
:root[data-theme="dark"] .gsplit.login-page .gside{--login-image-tint:rgba(31,35,83,.48)}
.gsplit.login-page .gside .muted{color:#dbeafe}
.gsplit.login-page .gside .chip.ai{background:rgba(15,23,42,.55);color:#c4b5fd}
@media(max-width:900px){.gside{display:none}.gform{padding:24px 16px}}
</style>
<script src="https://unpkg.com/lucide@0.469.0/dist/umd/lucide.min.js" defer></script>
</head>
<body>
<div class="gsplit @if(request()->routeIs('login'))login-page @endif">
  <div class="gform">
    <div class="row sp"><div class="brand" style="padding:0"><span class="logo"><i data-lucide="calendar-check" class="i"></i></span> EventFlow</div><div class="row">@include('partials.theme-toggle')</div></div>
    <div class="wrap">@yield('content')</div>
  </div>
  <div class="gside">
    <div class="col" style="max-width:420px;gap:16px;align-items:flex-start">
      <span class="chip ai"><i data-lucide="sparkles" class="i"></i></span>
      <div class="t32">{{ __('Rundown dan naskah acara, tersusun otomatis.') }}</div>
      <p class="t16 muted">{{ __('Isi data acara sekali, EventFlow menyusun rundown dan membantu menulis sambutan, pidato, naskah MC, dan doa.') }}</p>
    </div>
  </div>
</div>
<script>document.addEventListener('DOMContentLoaded',function(){ if(window.lucide) lucide.createIcons(); });</script>
@include('partials.theme-script')
</body>
</html>
