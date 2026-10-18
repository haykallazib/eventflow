@php
  $nav = 'dashboard';
@endphp
@extends('layouts.app')
@section('title', __('Ubah Acara'))
@section('heading', __('Ubah Acara'))
@section('content')
<form method="post" action="{{ route('events.update', $event) }}" class="card pad col" style="gap:32px">
  @csrf @method('PUT')
  @include('events._info', ['v' => [
    'name'=>$event->name,'theme'=>$event->theme,'location'=>$event->location,
    'event_date'=>$event->event_date->format('Y-m-d'),'start_time'=>$event->start_time,
    'duration_hours'=>$event->duration_minutes/60,'features'=>$event->features ?? [],
  ]])
  <label class="opt on"><input type="checkbox" name="rebuild" value="1" checked> {{ __('Susun ulang rundown dari data baru (menimpa penyuntingan manual)') }}</label>
  <div class="row sp"><a class="btn btn-g" href="{{ route('dashboard') }}">{{ __('Batal') }}</a><button class="btn btn-p btn-lg">{{ __('Simpan') }}</button></div>
</form>
@endsection
