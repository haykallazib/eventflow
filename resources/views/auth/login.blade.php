@extends('layouts.guest')
@section('title', __('Masuk'))
@section('content')
<div class="col" style="gap:8px"><h1 class="t32">{{ __('Masuk') }}</h1><p class="muted t16">{{ __('Masuk untuk mengelola acara Anda.') }}</p></div>
@if(session('status'))
  <div class="toast alert-ok">{{ __(session('status')) }}</div>
@endif
<form method="post" action="{{ route('login') }}" class="col" style="gap:16px">
  @csrf
  <div><label class="lbl" for="username">{{ __('Nama pengguna') }}</label>
    <input class="inp {{ $errors->has('username')?'err':'' }}" id="username" name="username" value="{{ old('username') }}" autocomplete="username" autofocus>
    @error('username')<div class="errtxt">{{ $message }}</div>@enderror</div>
  <div><label class="lbl" for="password">{{ __('Kata sandi') }}</label>
    <input class="inp" type="password" id="password" name="password" autocomplete="current-password">
    @error('password')<div class="errtxt">{{ $message }}</div>@enderror</div>
  <label class="row t14"><input type="checkbox" name="remember" value="1"> {{ __('Ingat saya') }}</label>
  <button class="btn btn-p btn-lg btn-block">{{ __('Masuk') }}</button>
</form>
<p class="t14 muted">{{ __('Belum punya akun?') }} <a class="pri b" href="{{ route('register') }}">{{ __('Daftar') }}</a></p>
@endsection
