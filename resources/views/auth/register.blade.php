@extends('layouts.guest')
@section('title', __('Daftar'))
@section('content')
<div class="col" style="gap:8px"><h1 class="t32">{{ __('Daftar') }}</h1><p class="muted t16">{{ __('Buat akun untuk mulai menyusun acara.') }}</p></div>
<form method="post" action="{{ route('register') }}" class="col" style="gap:16px">
  @csrf
  <div><label class="lbl" for="name">{{ __('Nama lengkap') }} <span class="muted">({{ __('opsional') }})</span></label>
    <input class="inp" id="name" name="name" value="{{ old('name') }}" autocomplete="name"></div>
  <div><label class="lbl" for="username">{{ __('Nama pengguna') }}</label>
    <input class="inp {{ $errors->has('username')?'err':'' }}" id="username" name="username" value="{{ old('username') }}" autocomplete="username">
    @error('username')<div class="errtxt">{{ __($message) }}</div>@enderror</div>
  <div><label class="lbl" for="password">{{ __('Kata sandi') }}</label>
    <input class="inp {{ $errors->has('password')?'err':'' }}" type="password" id="password" name="password" autocomplete="new-password">
    @error('password')<div class="errtxt">{{ __($message) }}</div>@enderror</div>
  <div><label class="lbl" for="password_confirmation">{{ __('Ulangi kata sandi') }}</label>
    <input class="inp" type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password"></div>
  <button class="btn btn-p btn-lg btn-block">{{ __('Daftar') }}</button>
</form>
<p class="t14 muted">{{ __('Sudah punya akun?') }} <a class="pri b" href="{{ route('login') }}">{{ __('Masuk') }}</a></p>
@endsection
